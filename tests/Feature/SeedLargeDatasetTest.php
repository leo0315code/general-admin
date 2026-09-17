<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 造数命令守卫回归（UI 现代化重构 · T02）
 *
 * 覆盖：
 * - APP_ENV=testing 下直接拒绝执行（避免污染测试数据）；
 * - APP_ENV=production 下必须加 --force 才执行；
 * - 加 --force 后可成功执行小规模造数（count=1，验证批量插入链路可用）；
 * - 命名空间隔离 + 起始编号接续：既有 test+ 用户存在时，新造数从 max 后缀+1 接续，
 *   且名称用「压测用户」前缀，不与基座「测试用户」冲突（缺陷 1 修复回归）。
 */
class SeedLargeDatasetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_refuses_to_run_in_testing_environment(): void
    {
        $this->artisan('app:seed-large-dataset', ['--count' => 10])
            ->expectsOutputToContain('测试环境禁止执行')
            ->assertExitCode(1);
    }

    public function test_refuses_to_run_in_production_without_force(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('app:seed-large-dataset', ['--count' => 10])
            ->expectsOutputToContain('生产环境禁止执行')
            ->assertExitCode(1);

        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_runs_small_seed_in_production_with_force(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('app:seed-large-dataset', ['--count' => 1, '--with-posts' => 0, '--force' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'test+0@example.com']);

        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_continuation_from_existing_test_users_and_name_isolation(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // 预置既有 test+ 用户（模拟已跑过造数 / 基座数据），后缀 0..4
        for ($i = 0; $i <= 4; $i++) {
            User::factory()->create(['email' => "test+{$i}@example.com", 'name' => "压测用户{$i}"]);
        }

        $this->artisan('app:seed-large-dataset', ['--count' => 3, '--with-posts' => 0, '--force' => true])
            ->assertExitCode(0);

        // 接续：应从后缀 5 开始，而不是从 0 撞唯一键
        $this->assertDatabaseHas('users', ['email' => 'test+5@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'test+7@example.com']);

        // 命名空间隔离：名称用「压测用户」，与基座「测试用户」不冲突
        $this->assertSame(8, User::query()->where('email', 'like', 'test+%@example.com')->count());
        $this->assertSame(0, User::query()->where('name', '测试用户')->count(), '基座测试用户不应被重复生成');

        $this->app->detectEnvironment(fn () => 'testing');
    }
}
