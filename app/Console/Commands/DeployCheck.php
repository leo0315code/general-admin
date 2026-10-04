<?php

namespace App\Console\Commands;

use App\Support\BackupTarget;
use App\Support\DumpBinary;
use App\Support\HealthCheck;
use App\Support\WsTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Predis\Client;

/**
 * 上线前自检
 *
 * 为什么要有这个命令：部署检查单（docs/deployment-checklist.md）里绝大多数项
 * 都能被机器判定，靠人眼勾迟早会漏。本命令把可自动验证的项一次性跑完，
 * 按 阻断 / 建议 / 通过 三级输出，阻断项存在时退出码非 0（可直接进 CI）。
 *
 * 用法：
 *   php artisan deploy:check              # 只把「阻断级」记为失败
 *   php artisan deploy:check --strict     # 建议级也算失败（CI 卡得更严）
 *   php artisan deploy:check --json       # 输出 JSON，便于接入监控/流水线
 *
 * 设计取舍：
 * - 「只在生产才要求」的项（如 APP_DEBUG=false）在非生产环境下自动降级为建议级，
 *   否则本地开发跑一次就满屏 FAIL，反而没人看了；
 * - 运行时自检（数据库/迁移/备份/磁盘/队列）直接复用 HealthCheck，
 *   保证命令行看到的结果与 /health/detailed 完全一致，不出现两套判定逻辑。
 */
class DeployCheck extends Command
{
    /** @var string */
    protected $signature = 'deploy:check
                            {--strict : 建议级（WARN）也视为失败}
                            {--json : 以 JSON 输出，便于流水线解析}';

    /** @var string */
    protected $description = '上线前自检：环境与运行时的阻断项 / 建议项（可进 CI）';

    /** 结果等级 */
    private const OK = 'ok';

    private const WARN = 'warn';

    private const FAIL = 'fail';

    /** @var list<array{name:string,hint:string,level:string,value:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkEnvironment();
        $this->checkRuntime();
        $this->checkOperations();

        $counts = [
            self::OK => 0,
            self::WARN => 0,
            self::FAIL => 0,
        ];

        foreach ($this->results as $row) {
            $counts[$row['level']]++;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'environment' => (string) config('app.env'),
                'passed' => $counts[self::FAIL] === 0,
                'counts' => $counts,
                'checks' => $this->results,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $this->exitCode($counts);
        }

        $this->table(
            ['状态', '检查项', '当前值 / 说明'],
            array_map(fn (array $r) => [$this->badge($r['level']), $r['name'], $r['hint']], $this->results),
        );

        $this->newLine();
        $this->line(sprintf(
            '通过 %d · 建议 %d · 阻断 %d（环境：%s）',
            $counts[self::OK],
            $counts[self::WARN],
            $counts[self::FAIL],
            (string) config('app.env'),
        ));

        if ($counts[self::FAIL] === 0 && $counts[self::WARN] === 0) {
            $this->info('✅ 全部检查通过，可以上线。');
        } else {
            $this->warn('详细清单与人工核对项见 docs/deployment-checklist.md');
        }

        return $this->exitCode($counts);
    }

    private function exitCode(array $counts): int
    {
        $strict = (bool) $this->option('strict');

        if ($counts[self::FAIL] > 0) {
            return self::FAILURE;
        }

        return $strict && $counts[self::WARN] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function badge(string $level): string
    {
        return match ($level) {
            self::OK => '<info>通过</info>',
            self::WARN => '<comment>建议</comment>',
            default => '<fg=red>阻断</fg=red>',
        };
    }

    /** 记录一条结果；非生产环境下把「仅生产要求」的阻断降级为建议 */
    private function record(string $name, string $level, string $hint, bool $productionOnly = false): void
    {
        if ($productionOnly && $level === self::FAIL && ! $this->isProduction()) {
            $level = self::WARN;
            $hint .= '（仅生产要求，当前环境已降级为建议）';
        }

        $this->results[] = ['name' => $name, 'hint' => $hint, 'level' => $level, 'value' => $hint];
    }

    private function isProduction(): bool
    {
        return config('app.env') === 'production';
    }

    /** 一、环境与配置（清单第 1、2 节） */
    private function checkEnvironment(): void
    {
        $this->record(
            'APP_ENV=production',
            $this->isProduction() ? self::OK : self::WARN,
            '当前 '.config('app.env'),
        );

        $this->record(
            'APP_DEBUG=false',
            config('app.debug') ? self::FAIL : self::OK,
            config('app.debug') ? '开启中：会泄露堆栈与配置线索' : '已关闭',
            productionOnly: true,
        );

        $this->record(
            'APP_KEY 已设置',
            blank(config('app.key')) ? self::FAIL : self::OK,
            blank(config('app.key')) ? '缺失：会话与加密全部不可用' : '已设置',
        );

        $this->record(
            'SESSION_ENCRYPT=true',
            config('session.encrypt') ? self::OK : self::FAIL,
            config('session.encrypt') ? '会话加密落库' : '未加密：会话含登录态与角色信息',
            productionOnly: true,
        );

        $this->record(
            'SESSION_SECURE_COOKIE=true',
            config('session.secure') ? self::OK : self::FAIL,
            config('session.secure') ? '仅 HTTPS 传输' : '未开启：Cookie 可能被明文截获',
            productionOnly: true,
        );

        $this->checkEnvPlaceholders();
        $this->checkBuiltAssets();
        $this->checkConfigCached();
    }

    /** `.env` 里残留占位符（REPLACE_ME）会导致生产用错配置 */
    private function checkEnvPlaceholders(): void
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            $this->record('.env 占位符', self::WARN, '未找到 .env（可能走环境变量注入）');

            return;
        }

        $left = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
            if (str_contains((string) $line, 'REPLACE_ME')) {
                $left[] = trim((string) $line);
            }
        }

        $this->record(
            '.env 无未替换占位符',
            $left === [] ? self::OK : self::FAIL,
            $left === [] ? '已全部替换' : '仍存在 '.count($left).' 处：'.implode(' / ', array_slice($left, 0, 3)),
            productionOnly: true,
        );
    }

    /** 前端产物缺失会导致页面样式与交互全废，且不易在后端日志里发现 */
    private function checkBuiltAssets(): void
    {
        $manifest = public_path('build/manifest.json');

        $this->record(
            '前端产物已构建',
            is_file($manifest) ? self::OK : self::FAIL,
            is_file($manifest) ? 'public/build/manifest.json 存在' : '缺 public/build：需 npm run build',
            productionOnly: true,
        );
    }

    /** 生产应固化配置缓存，否则每请求都要重新解析全部 config */
    private function checkConfigCached(): void
    {
        $cached = is_file(base_path('bootstrap/cache/config.php'));

        $this->record(
            '配置已缓存',
            $cached ? self::OK : self::WARN,
            $cached ? 'bootstrap/cache/config.php 存在' : '建议部署后执行 php artisan config:cache',
        );
    }

    /** 二、运行时自检：直接复用 HealthCheck，与 /health/detailed 同源 */
    private function checkRuntime(): void
    {
        $report = (new HealthCheck)->detailed();

        foreach ($report['checks'] as $key => $check) {
            $level = match ($check['status']) {
                'ok' => self::OK,
                'degraded' => self::WARN,
                default => self::FAIL,
            };

            $this->record('运行时 · '.$key, $level, (string) ($check['message'] ?? ''));
        }
    }

    /** 三、运维项（清单第 6、7、8 节） */
    private function checkOperations(): void
    {
        $dumpPath = DumpBinary::directory();

        $this->record(
            'mysqldump 可用',
            $dumpPath === null ? self::FAIL : self::OK,
            $dumpPath === null ? '未找到：备份会失败并告警，不会静默产出空备份' : $dumpPath,
        );

        $this->record(
            '异地备份已启用',
            BackupTarget::hasOffsite() ? self::OK : self::WARN,
            BackupTarget::hasOffsite()
                ? '目标磁盘：'.implode(', ', BackupTarget::disks())
                : '仅本地磁盘：磁盘故障会与备份一起丢（补齐 4 项 OSS 变量即开启）',
        );

        $this->checkFailedJobs();
        $this->checkCors();
        $this->checkWebSocketTicket();
    }

    /**
     * WS 票据必须落在跨进程共享的介质上：php-fpm 签发、常驻 Worker 消费，
     * array / null 驱动下票据永远兑不出来，现象是「连上就断」却查不到原因。
     */
    private function checkWebSocketTicket(): void
    {
        if (! (bool) config('websocket.enabled')) {
            $this->record('WS 票据 store', self::OK, '未启用 WS（WS_ENABLED=false），票据不参与');

            return;
        }

        // 指定了 redis 却没有客户端实现，票据读写会直接抛异常（phpredis 扩展与 predis 二选一）
        if (WsTicket::storeName() === 'redis' && ! $this->redisClientAvailable()) {
            $this->record(
                'WS 票据 store',
                self::FAIL,
                '票据 store 为 redis，但 PHP 既没有 phpredis 扩展也没有 predis 包；票据读写会抛异常，WS 全站不可用',
            );

            return;
        }

        $problem = WsTicket::problem();

        $level = match (true) {
            $problem === null => self::OK,
            // database 能用，只是代价高；array/null 是根本不能用，必须阻断
            WsTicket::usable() => self::WARN,
            default => self::FAIL,
        };

        $this->record('WS 票据 store', $level, $problem ?? 'store: '.WsTicket::storeName());
    }

    private function redisClientAvailable(): bool
    {
        return extension_loaded('redis') || class_exists(Client::class);
    }

    private function checkFailedJobs(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->record('队列失败任务', self::OK, '无 failed_jobs 表（当前队列驱动可能非 database）');

            return;
        }

        $failed = DB::table('failed_jobs')->count();

        $this->record(
            '队列无失败堆积',
            $failed === 0 ? self::OK : self::WARN,
            $failed === 0 ? '0 条' : $failed.' 条失败任务待处理：php artisan queue:retry all',
        );
    }

    /** 白名单写成 `*` 等于对全网放开跨域 */
    private function checkCors(): void
    {
        $origins = (array) config('cors.allowed_origins', []);

        $wildcard = in_array('*', $origins, true);

        $this->record(
            'CORS 未通配',
            $wildcard ? self::FAIL : self::OK,
            $wildcard ? 'allowed_origins 含 *：任意站点可跨域请求' : '白名单 '.count($origins).' 条（空＝不放行任何跨域）',
        );
    }
}
