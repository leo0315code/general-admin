<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class DeployCheckTest extends TestCase
{
    // 运行时自检要读真实表（migrations / failed_jobs），必须有库有表
    use RefreshDatabase;

    public function test_json_output_is_parseable_and_carries_counts(): void
    {
        Artisan::call('deploy:check --json');

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('environment', $decoded);
        $this->assertArrayHasKey('passed', $decoded);
        $this->assertArrayHasKey('checks', $decoded);
        $this->assertNotEmpty($decoded['checks']);
        $this->assertSame(
            array_sum($decoded['counts']),
            count($decoded['checks']),
            '计数之和应等于检查项总数',
        );
    }

    public function test_production_only_item_is_blocking_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', true);

        // 生产环境开着 debug 属阻断级：会泄露堆栈与配置线索
        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function test_production_only_item_is_downgraded_outside_production(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.debug', true);

        // 本地开 debug 是常态：降级为建议，不应让命令失败
        $this->artisan('deploy:check')->assertExitCode(0);
    }

    public function test_strict_mode_treats_warnings_as_failure(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.debug', true); // 至少产生一条降级后的建议项

        $this->artisan('deploy:check --strict')->assertExitCode(1);
    }

    public function test_wildcard_cors_origin_is_blocking(): void
    {
        Config::set('cors.allowed_origins', ['*']);

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function test_empty_app_key_is_blocking(): void
    {
        Config::set('app.key', '');

        $this->artisan('deploy:check')->assertExitCode(1);
    }

    public function test_every_check_reports_a_hint(): void
    {
        Artisan::call('deploy:check --json');

        foreach (json_decode(Artisan::output(), true)['checks'] as $check) {
            $this->assertArrayHasKey('name', $check);
            $this->assertArrayHasKey('level', $check);
            $this->assertContains($check['level'], ['ok', 'warn', 'fail']);
            $this->assertNotEmpty($check['hint'], $check['name'].' 缺少说明，运维无法据此处理');
        }
    }
}
