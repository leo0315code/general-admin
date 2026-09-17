<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 仪表盘功能测试
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // 种子：角色/权限/用户/文章
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_admin_can_access_dashboard_and_see_stats(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('仪表盘');
        $response->assertSee('用户总数');
        $response->assertSee('角色数');
        $response->assertSee('文章总数');
        $response->assertSee('已发布文章');

        // 统计数字与数据库一致
        $response->assertSee((string) User::query()->count());
        $response->assertSee((string) Role::query()->count());
        $response->assertSee((string) Post::query()->count());
        $response->assertSee((string) Post::query()->published()->count());
    }

    public function test_editor_can_access_dashboard_with_dashboard_view_permission(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)->get(route('dashboard'))->assertOk();
    }

    public function test_user_without_dashboard_view_permission_cannot_access_dashboard(): void
    {
        // 创建一个没有任何权限的角色与用户
        $role = Role::query()->create(['name' => 'no-perm']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }
}
