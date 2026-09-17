<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * QA 独立冒烟验证（独立于工程师自测覆盖，持续维护）
 *
 * 重点覆盖关键路径：
 * - 认证重定向链路
 * - RBAC：admin 全量访问、editor 受限访问（含直接 POST 拦截，而非仅 UI 隐藏）
 * - 侧边栏菜单按权限显隐
 * - 软删除文章不出现在列表
 * - 角色权限 checkbox 保存后端到端生效
 */
class QaIndependentSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    protected function editor(): User
    {
        return User::query()->where('email', 'editor@example.com')->firstOrFail();
    }

    // ---- 认证链路 ----

    public function test_guest_visiting_dashboard_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    // ---- RBAC 访问矩阵 ----

    public function test_admin_can_access_all_management_pages(): void
    {
        $admin = $this->admin();

        foreach (['users.index', 'roles.index', 'posts.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_editor_access_matrix(): void
    {
        $editor = $this->editor();

        // 允许：仪表盘 + 文章管理
        $this->actingAs($editor)->get(route('dashboard'))->assertOk();
        $this->actingAs($editor)->get(route('posts.index'))->assertOk();

        // 拒绝：用户管理 / 角色管理
        $this->actingAs($editor)->get(route('users.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('roles.index'))->assertForbidden();
    }

    // ---- 直接 POST 拦截（不只 UI 隐藏） ----

    public function test_editor_direct_post_to_users_store_is_blocked(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('users.store'), [
            'name' => '越权用户',
            'email' => 'x@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_editor_direct_delete_of_user_is_blocked(): void
    {
        $editor = $this->editor();
        $target = User::factory()->create();

        $this->actingAs($editor)->delete(route('users.destroy', $target))->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_editor_direct_post_to_roles_store_is_blocked(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('roles.store'), [
            'name' => 'evil',
        ])->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'evil']);
    }

    public function test_user_without_post_manage_cannot_post_posts(): void
    {
        $user = User::factory()->create(); // 无任何角色/权限

        $this->actingAs($user)->post(route('posts.store'), [
            'title' => '越权文章',
            'content' => '内容',
            'status' => Post::STATUS_DRAFT,
        ])->assertForbidden();

        $this->assertDatabaseMissing('posts', ['title' => '越权文章']);
    }

    // ---- 侧边栏菜单按权限显隐 ----

    public function test_editor_sidebar_hides_unauthorized_menu_items(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('文章管理');        // 有 post.manage → 显示
        $response->assertDontSee('用户管理');    // 无 user.manage → 隐藏
        $response->assertDontSee('角色管理');    // 无 role.manage → 隐藏
        $response->assertDontSee(route('users.index'));
        $response->assertDontSee(route('roles.index'));
    }

    public function test_admin_sidebar_shows_all_menu_items(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('用户管理');
        $response->assertSee('角色管理');
        $response->assertSee('文章管理');
    }

    // ---- 软删除 ----

    public function test_soft_deleted_post_does_not_appear_in_list(): void
    {
        $admin = $this->admin();
        $post = Post::factory()->create(['user_id' => $admin->id, 'title' => '将被软删除的文章']);

        $this->actingAs($admin)->delete(route('posts.destroy', $post));

        $this->assertSoftDeleted('posts', ['id' => $post->id]);

        // 模型层：默认查询（列表所用）已排除软删除记录，仅 withTrashed 可见
        $this->assertFalse(Post::query()->whereKey($post->id)->exists());
        $this->assertTrue(Post::withTrashed()->whereKey($post->id)->exists());

        // 页面层：首次 GET 会消费删除成功的 flash（含标题），再请求一次确认列表表格不渲染该文章
        $this->actingAs($admin)->get(route('posts.index'))->assertOk();
        $this->actingAs($admin)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertDontSee('将被软删除的文章');
    }

    // ---- 角色权限 checkbox 保存后端到端生效 ----

    public function test_role_permission_checkbox_change_takes_effect_end_to_end(): void
    {
        $admin = $this->admin();
        $dashboardPerm = Permission::query()->where('name', 'dashboard.view')->firstOrFail();
        $postPerm = Permission::query()->where('name', 'post.manage')->firstOrFail();

        // 1. 新建角色：仅 dashboard.view
        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'viewer',
            'description' => '只读角色',
            'permissions' => [$dashboardPerm->id],
        ]);

        $role = Role::query()->where('name', 'viewer')->firstOrFail();
        $user = User::factory()->create();
        $user->assignRole($role);

        // 2. 只读角色：能看仪表盘，不能管文章
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
        $this->actingAs($user->fresh())->get(route('posts.index'))->assertForbidden();

        // 3. 通过后台编辑角色勾选 post.manage → 保存后立即生效
        $this->actingAs($admin)->patch(route('roles.update', $role), [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => [$dashboardPerm->id, $postPerm->id],
        ])->assertRedirect(route('roles.edit', $role));

        $this->actingAs($user->fresh())->get(route('posts.index'))->assertOk();
    }

    // ---- 记录性：role.manage 与 admin 角色中间件的可见性/可达性不一致 ----

    public function test_documents_role_manage_menu_visibility_vs_admin_gate_inconsistency(): void
    {
        $admin = $this->admin();
        $rolePerm = Permission::query()->where('name', 'role.manage')->firstOrFail();
        $dashboardPerm = Permission::query()->where('name', 'dashboard.view')->firstOrFail();

        // 自定义角色授予 dashboard.view + role.manage（非 admin 角色）
        $role = Role::query()->create(['name' => 'role-mgr', 'description' => '角色管理员']);
        $role->givePermissionTo([$rolePerm, $dashboardPerm]);

        $user = User::factory()->create();
        $user->assignRole($role);

        // 现状：侧边栏因 role.manage 显示「角色管理」，但点击因 admin 角色中间件被 403 拒绝
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('角色管理');
        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
    }
}
