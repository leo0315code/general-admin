<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 角色管理功能测试（基于 spatie/laravel-permission）：
 * CRUD + 权限分配 + 内置 admin 角色保护
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_admin_can_view_role_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('admin')
            ->assertSee('editor');
    }

    public function test_admin_can_create_role_with_permissions(): void
    {
        $dashboardPerm = Permission::query()->where('name', 'dashboard.view')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('roles.store'), [
                'name' => 'operator',
                'description' => '运营角色',
                'permissions' => [$dashboardPerm->id],
            ])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', ['name' => 'operator', 'description' => '运营角色']);
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => Role::query()->where('name', 'operator')->firstOrFail()->id,
            'permission_id' => $dashboardPerm->id,
        ]);
    }

    public function test_duplicate_role_name_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('roles.store'), [
                'name' => 'admin',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $role = Role::query()->where('name', 'editor')->firstOrFail();
        $userPerm = Permission::query()->where('name', 'user.manage')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('roles.update', $role), [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => [$userPerm->id],
            ])
            ->assertRedirect(route('roles.edit', $role));

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $userPerm->id,
        ]);
    }

    public function test_admin_role_edit_page_renders(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('roles.edit', $adminRole))
            ->assertOk()
            ->assertSee('admin');
    }

    public function test_admin_role_can_be_updated_without_changing_name(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $dashboardPerm = Permission::query()->where('name', 'dashboard.view')->firstOrFail();

        // 模拟真实表单：name 只读随表单提交（值保持 admin）
        $this->actingAs($this->admin)
            ->patch(route('roles.update', $adminRole), [
                'name' => 'admin',
                'description' => '拥有系统全部权限',
                'permissions' => [$dashboardPerm->id],
            ])
            ->assertRedirect(route('roles.edit', $adminRole))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'name' => 'admin']);
    }

    public function test_admin_role_name_cannot_be_changed(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('roles.update', $adminRole), [
                'name' => 'super-admin',
                'description' => '试图改名',
                'permissions' => [],
            ])
            ->assertRedirect(route('roles.edit', $adminRole))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id, 'name' => 'admin']);
    }

    public function test_builtin_admin_role_cannot_be_deleted(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $adminRole))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
    }

    public function test_role_assigned_to_users_cannot_be_deleted(): void
    {
        $editorRole = Role::query()->where('name', 'editor')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('roles.destroy', $editorRole))
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $editorRole->id]);
    }

    public function test_role_without_role_manage_permission_is_forbidden(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        // editor 默认只有 dashboard.view + post.manage，无 role.manage → 菜单不显示、页面 403
        $this->actingAs($editor)->get(route('roles.index'))->assertForbidden();
    }

    public function test_role_with_role_manage_permission_can_access_role_management(): void
    {
        // 权限语义统一后（路由与菜单同源），授予 role.manage 即可访问角色管理
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();
        $editorRole = Role::query()->where('name', 'editor')->firstOrFail();
        $editorRole->givePermissionTo(Permission::query()->where('name', 'role.manage')->firstOrFail());

        $this->actingAs($editor->fresh())->get(route('roles.index'))->assertOk();
    }
}
