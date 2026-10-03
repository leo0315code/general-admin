<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * 健康检查支持类
 *
 * 设计要点：
 * 1. 所有单项检查都独立捕获 Throwable，**一项挂了不影响其余项**——
 *    监控探针本身绝不能因为某个子系统故障而 500。
 * 2. 整体 status 由「fail 数量」决定：全部 ok → ok；有 fail → degraded；有 error → error。
 * 3. 单项返回 status + message + meta，meta 只放运维关心的数字（延迟/天数/挂起数），
 *    不放敏感信息（密码、连接串、文件路径）。
 */
class HealthCheck
{
    /** 生产阈值默认值（可被 config('app.health_*') 覆盖） */
    private const DISK_FREE_MIN_MB = 500;

    private const QUEUE_PENDING_MAX = 50;

    /** 缓存读写测试用的键名（写完立即删，避免污染） */
    private const CACHE_PROBE_KEY = '__health_probe__';

    /**
     * 判断 IP 是否属于内部网络（供 /health/detailed 放行判断）。
     *
     * 为什么不用 filter_var 的 FLAG：NO_PRIV_RANGE 与 NO_RES_RANGE 是**排除式**
     * 标志，按位或合并后变成「既排除私有段又排除保留段」——127.0.0.1 属保留段、
     * 10.0.0.0/8 属私有段，叠加后反而把所有内网都排除了（实测 8.8.8.8 反而是
     * 唯一通过者）。这两个标志根本不能用于「是否内网」的判定，改用 CIDR 白名单。
     */
    public static function isInternalIp(?string $ip): bool
    {
        if (! is_string($ip) || $ip === '') {
            return false;
        }

        // IPv4 内网 + 保留段
        $v4Ranges = [
            '127.0.0.0/8',    // loopback
            '10.0.0.0/8',     // 私网
            '172.16.0.0/12',  // 私网
            '192.168.0.0/16', // 私网
            '169.254.0.0/16', // 链路本地
        ];

        foreach ($v4Ranges as $cidr) {
            if (self::ipv4InCidr($ip, $cidr)) {
                return true;
            }
        }

        // IPv6 loopback / 链路本地 / 唯一本地地址
        if (in_array($ip, ['::1'], true)
            || str_starts_with(strtolower($ip), 'fe80:')
            || str_starts_with(strtolower($ip), 'fc')
            || str_starts_with(strtolower($ip), 'fd')) {
            return true;
        }

        return false;
    }

    /**
     * 判断 IPv4 地址是否落在 CIDR 内（不依赖外部包，几行实现）。
     */
    private static function ipv4InCidr(string $ip, string $cidr): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        [$network, $maskBits] = explode('/', $cidr);
        $ipLong = ip2long($ip);
        $netLong = ip2long($network);

        if ($ipLong === false || $netLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $maskBits);

        return ($ipLong & $mask) === ($netLong & $mask);
    }

    /**
     * 基础健康（免登录，监控探活）。
     * 只证明「应用活着」，不做重资源探测。
     *
     * @return array<string, mixed>
     */
    public function basic(): array
    {
        return [
            'status' => 'ok',
            'app' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
            'php' => PHP_VERSION,
            'environment' => app()->environment(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * 深度自检（逐项探测子系统）。
     *
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function detailed(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'migrations' => $this->checkMigrations(),
            'cache' => $this->checkCache(),
            'backup' => $this->checkBackup(),
            'storage' => $this->checkStorage(),
            'disk' => $this->checkDiskSpace(),
            'queue' => $this->checkQueue(),
        ];

        $worst = 'ok';
        $rank = ['ok' => 0, 'degraded' => 1, 'error' => 2];

        foreach ($checks as $check) {
            if (($rank[$check['status']] ?? 2) > $rank[$worst]) {
                $worst = $check['status'];
            }
        }

        return ['status' => $worst, 'checks' => $checks];
    }

    /** 数据库：真实跑一次轻量查询（不是仅配置探测） */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);

            return ['status' => 'ok', 'message' => '连接正常', 'meta' => ['latency_ms' => $latency]];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => '数据库不可达', 'meta' => []];
        }
    }

    /** 迁移：确认没有 pending 迁移（部署后忘跑 migrate 是常见事故） */
    private function checkMigrations(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return ['status' => 'error', 'message' => 'migrations 表不存在', 'meta' => []];
            }

            // 用 MigrationRepository 的 api 拿已跑列表，再与磁盘迁移文件比对
            $ran = app('migration.repository')->getRan();
            $files = $this->migrationFiles();
            $pending = array_diff($files, $ran);
            $count = count($pending);

            return [
                'status' => $count === 0 ? 'ok' : 'degraded',
                'message' => $count === 0 ? '迁移已全部执行' : "存在 {$count} 个未执行迁移",
                'meta' => ['pending' => $count],
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => '无法读取迁移状态', 'meta' => []];
        }
    }

    /** 缓存：真实写入再读出校验（不是仅 ping），测完立即清理 */
    private function checkCache(): array
    {
        try {
            $value = 'probe_'.uniqid();
            Cache::put(self::CACHE_PROBE_KEY, $value, 10);
            $read = Cache::get(self::CACHE_PROBE_KEY);
            Cache::forget(self::CACHE_PROBE_KEY);

            $ok = $read === $value;

            return [
                'status' => $ok ? 'ok' : 'degraded',
                'message' => $ok ? '读写一致' : '写入后读取不一致',
                'meta' => ['driver' => config('cache.default')],
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => '缓存不可读写', 'meta' => ['driver' => config('cache.default')]];
        }
    }

    /** 备份：接上轮 spatie 成果，检查最新备份是否在新鲜度窗口内 */
    private function checkBackup(): array
    {
        try {
            if (! config('filesystems.disks.backups')) {
                return ['status' => 'degraded', 'message' => '备份磁盘未配置', 'meta' => []];
            }

            $disk = Storage::disk('backups');
            $files = $disk->allFiles();

            if ($files === []) {
                return ['status' => 'degraded', 'message' => '尚未生成任何备份', 'meta' => ['count' => 0]];
            }

            // 找最新 zip（备份归档），忽略日志/清单等附属文件
            $zips = array_values(array_filter($files, fn ($f) => str_ends_with($f, '.zip')));

            if ($zips === []) {
                return ['status' => 'degraded', 'message' => '目录存在但无备份归档', 'meta' => []];
            }

            $latest = collect($zips)
                ->map(fn ($f) => ['path' => $f, 'mtime' => $disk->lastModified($f)])
                ->sortByDesc('mtime')
                ->first();

            $ageDays = $latest['mtime'] > 0 ? (now()->timestamp - $latest['mtime']) / 86400 : 999;
            $maxAge = $this->backupMaxAgeDays();

            return [
                'status' => $ageDays <= $maxAge ? 'ok' : 'degraded',
                'message' => sprintf('最新备份 %s 天前（阈值 %s 天）', round($ageDays, 1), round($maxAge, 1)),
                'meta' => ['age_days' => round($ageDays, 1), 'max_age_days' => round($maxAge, 1), 'count' => count($zips)],
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => '备份检查失败', 'meta' => []];
        }
    }

    /** 存储：关键目录实际可写（不是仅配置存在） */
    private function checkStorage(): array
    {
        $dirs = [
            'storage/app/private/backups',
            'storage/app/private',
            'storage/framework/cache',
            'storage/logs',
            'bootstrap/cache',
        ];

        $unwritable = [];

        foreach ($dirs as $dir) {
            $path = base_path($dir);

            if (! is_dir($path) || ! is_writable($path)) {
                $unwritable[] = $dir;
            }
        }

        return [
            'status' => $unwritable === [] ? 'ok' : 'degraded',
            'message' => $unwritable === [] ? '关键目录均可写' : '目录不可写: '.implode(', ', $unwritable),
            'meta' => ['unwritable' => $unwritable],
        ];
    }

    /** 磁盘剩余：剩余空间不足时影响备份、日志、上传 */
    private function checkDiskSpace(): array
    {
        try {
            $path = base_path();
            $free = disk_free_space($path);
            $total = disk_total_space($path);

            if ($free === false || $total === false) {
                return ['status' => 'degraded', 'message' => '无法读取磁盘空间', 'meta' => []];
            }

            $freeMb = round($free / 1048576, 1);
            $usedPct = $total > 0 ? round((($total - $free) / $total) * 100, 1) : null;
            $threshold = (float) config('app.health_disk_free_min_mb', self::DISK_FREE_MIN_MB);

            return [
                'status' => $freeMb >= $threshold ? 'ok' : 'degraded',
                'message' => "剩余 {$freeMb} MB（阈值 {$threshold} MB，已用 {$usedPct}%）",
                'meta' => ['free_mb' => $freeMb, 'used_pct' => $usedPct, 'threshold_mb' => $threshold],
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => '磁盘检查失败', 'meta' => []];
        }
    }

    /** 队列：挂起任务数是否积压（含 failed_jobs） */
    private function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $meta = ['connection' => $connection];

            if ($connection === 'database') {
                $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
                $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
                $meta += ['pending' => $pending, 'failed' => $failed];

                $threshold = (int) config('app.health_queue_pending_max', self::QUEUE_PENDING_MAX);

                return [
                    'status' => $pending <= $threshold ? 'ok' : 'degraded',
                    'message' => "挂起 {$pending} 条 / 失败 {$failed} 条（阈值 {$threshold}）",
                    'meta' => $meta,
                ];
            }

            // redis / sqs 等非 database 驱动：只做存在性声明，不做深探测
            return ['status' => 'ok', 'message' => "驱动 {$connection}（未探测积压）", 'meta' => $meta];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => '队列检查失败', 'meta' => []];
        }
    }

    /** 读取所有迁移文件名（不含扩展名） */
    private function migrationFiles(): array
    {
        return collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn ($p) => pathinfo($p, PATHINFO_FILENAME))
            ->all();
    }

    /** 从 backup 配置提取新鲜度阈值（键是类名，值是天数） */
    private function backupMaxAgeDays(): float
    {
        $checks = config('backup.monitor_backups.0.health_checks', []);

        foreach ($checks as $class => $value) {
            if (str_ends_with($class, 'MaximumAgeInDays')) {
                return (float) $value;
            }
        }

        return 1.0;
    }
}
