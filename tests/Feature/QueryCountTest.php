<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 列表页 N+1 回归（UI 现代化重构 · T03 / PERF-3）
 *
 * 断言用户/文章列表页的 SQL 数量为固定常数（≤8），不随行数增长 ——
 * 防止未来改造引入逐行关联查询（N+1）。
 *
 * 说明：Spatie 的 Gate::before 会在每次请求时加载「全部权限/角色」作 RBAC 判定
 * （跨请求固定的引导开销，生产环境由 PermissionRegistrar 缓存复用）。
 * 本测试在开启查询日志前预载权限集合，使测量聚焦于「列表查询路径」本身，
 * 避免把这份固定开销计入 N+1 判定。
 */
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_users_index_queries_are_constant(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        // 预载 RBAC 权限集合（等价于生产环境缓存命中），排除固定引导开销
        app(PermissionRegistrar::class)->getPermissions();
        $admin->can('user.manage');

        User::factory()->count(10)->create();

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $queries = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(8, $queries, "用户列表 SQL 数 {$queries} 超过 8，疑似引入 N+1");
    }

    public function test_posts_index_queries_are_constant(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        // 预载 RBAC 权限集合（等价于生产环境缓存命中），排除固定引导开销
        app(PermissionRegistrar::class)->getPermissions();
        $admin->can('user.manage');

        Post::factory()->count(10)->create(['user_id' => $admin->id]);

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('posts.index'))->assertOk();
        $queries = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(8, $queries, "文章列表 SQL 数 {$queries} 超过 8，疑似引入 N+1");
    }
}
