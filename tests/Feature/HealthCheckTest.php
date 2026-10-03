<?php

use App\Support\HealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /* ============ 基础端点 ============ */

    public function test_basic_endpoint_is_public_and_returns_expected_structure(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonStructure(['status', 'app', 'version', 'php', 'environment', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    /* ============ 深度端点访问控制 ============ */

    public function test_detailed_allows_loopback_ip(): void
    {
        // 自检结果依赖机器上是否有备份，用 fake 造一份"刚刚生成"的备份，
        // 让这条用例在任何机器上都能稳定得到 status=ok。
        Storage::fake('backups')->put(
            '通用管理后台/'.now()->format('Y-m-d-H-i-s').'.zip',
            'fake'
        );

        $response = $this->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonStructure(['status', 'checks'])
            ->assertJsonPath('status', 'ok');
    }

    public function test_detailed_rejects_public_ip_without_token(): void
    {
        $response = $this->call('GET', '/health/detailed', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);

        $response->assertForbidden()
            ->assertJson(['status' => 'error']);
    }

    public function test_detailed_allows_private_network_ip(): void
    {
        foreach (['10.0.0.5', '172.16.0.9', '192.168.1.10'] as $ip) {
            $response = $this->call('GET', '/health/detailed', [], [], [], ['REMOTE_ADDR' => $ip]);
            $response->assertOk();
        }
    }

    public function test_detailed_rejects_wrong_token(): void
    {
        config(['app.health_token' => 'real-token-123']);

        $response = $this->call(
            'GET',
            '/health/detailed',
            ['token' => 'wrong-token'],
            [], [],
            ['REMOTE_ADDR' => '8.8.8.8']
        );

        $response->assertForbidden();
    }

    public function test_detailed_allows_correct_token_from_public_ip(): void
    {
        config(['app.health_token' => 'real-token-123']);

        $response = $this->call(
            'GET',
            '/health/detailed',
            ['token' => 'real-token-123'],
            [], [],
            ['REMOTE_ADDR' => '8.8.8.8']
        );

        $response->assertOk();
    }

    /* ============ 深度端点内容 ============ */

    public function test_detailed_returns_all_seven_checks(): void
    {
        $response = $this->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'checks' => [
                    'database' => ['status', 'message', 'meta'],
                    'migrations' => ['status', 'message', 'meta'],
                    'cache' => ['status', 'message', 'meta'],
                    'backup' => ['status', 'message', 'meta'],
                    'storage' => ['status', 'message', 'meta'],
                    'disk' => ['status', 'message', 'meta'],
                    'queue' => ['status', 'message', 'meta'],
                ],
            ]);
    }

    public function test_detailed_database_check_has_latency(): void
    {
        $response = $this->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonStructure(['checks' => ['database' => ['meta' => ['latency_ms']]]]);
    }

    public function test_detailed_backup_check_reports_when_no_backups(): void
    {
        // 必须用 fake：若直接拿 Storage::disk('backups') 清空，删的是**真实备份文件**，
        // 而且会让后续依赖真实备份的用例随执行顺序随机失败（曾导致偶发 degraded）。
        Storage::fake('backups');

        $response = $this->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonPath('checks.backup.status', 'degraded')
            ->assertJsonPath('checks.backup.meta.count', 0);
    }

    public function test_detailed_backup_check_flags_stale_backup(): void
    {
        config(['backup.monitor_backups.0.health_checks' => [
            'App\Fake\MaximumAgeInDays' => 1,
        ]]);

        // 同样用 fake 目录，避免污染真实备份
        $disk = Storage::fake('backups');
        $disk->put('stale/2020-01-01-00-00-00.zip', 'old');
        $this->travelTo(now()->addDays(400));

        $response = $this->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonPath('checks.backup.status', 'degraded');
    }

    public function test_detailed_queue_check_counts_pending_and_failed(): void
    {
        // 直接调支持类而非走 HTTP：测试的插入与请求分属不同事务/连接，
        // 走 getJson 读不到本用例刚写入的行，会把逻辑正确的代码测成失败。
        // phpunit.xml 把 QUEUE_CONNECTION 固定为 sync，这里必须显式切成 database
        // 才能走到真正的计数分支（否则只会返回"未探测积压"）。
        Config::set('queue.default', 'database');
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->delete();

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'x',
            'failed_at' => now(),
        ]);

        $checks = (new HealthCheck)->detailed()['checks']['queue'];

        $this->assertSame(1, $checks['meta']['pending']);
        $this->assertSame(1, $checks['meta']['failed']);
        $this->assertSame('database', $checks['meta']['connection']);

        DB::table('jobs')->delete();
        DB::table('failed_jobs')->delete();
    }

    /* ============ 内网判定（isInternalIp 纯函数） ============ */

    public function test_is_internal_ip_matches_expected_networks(): void
    {
        $internal = ['127.0.0.1', '127.0.0.99', '10.0.0.1', '10.255.255.255', '172.16.0.1', '172.31.255.255', '192.168.1.1', '169.254.1.1', '::1', 'fe80::1', 'fc00::1', 'fd00::1'];
        $public = ['8.8.8.8', '1.1.1.1', '203.0.113.5', '9.255.255.255', '11.0.0.1', '172.32.0.1', '192.169.0.1'];

        foreach ($internal as $ip) {
            $this->assertTrue(HealthCheck::isInternalIp($ip), "{$ip} 应判定为内网");
        }

        foreach ($public as $ip) {
            $this->assertFalse(HealthCheck::isInternalIp($ip), "{$ip} 不应判定为内网");
        }
    }

    public function test_is_internal_ip_rejects_invalid_input(): void
    {
        $this->assertFalse(HealthCheck::isInternalIp(null));
        $this->assertFalse(HealthCheck::isInternalIp(''));
        $this->assertFalse(HealthCheck::isInternalIp('not-an-ip'));
        $this->assertFalse(HealthCheck::isInternalIp('256.256.256.256'));
    }
}
