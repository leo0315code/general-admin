<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RBAC 权限控制测试（基于 spatie/laravel-permission）：
 * hasRole / hasPermissionTo / can / 中间件 / Gate
 */
class PermissionControlTest extends TestCase
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

    public function test_admin_has_all_permissions(): void
    {
        $admin = $this->admin();

        $this->assertTrue($admin->hasRole('admin'));
        foreach (['dashboard.view', 'user.manage', 'post.manage', 'role.manage'] as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission));
            $this->assertTrue($admin->can($permission));
        }
    }

    public function test_editor_has_only_granted_permissions(): void
    {
        $editor = $this->editor();

        $this->assertTrue($editor->can('dashboard.view'));
        $this->assertTrue($editor->can('post.manage'));
        $this->assertFalse($editor->can('user.manage'));
        $this->assertFalse($editor->can('role.manage'));
    }

    public function test_gate_before_grants_admin_all_permissions(): void
    {
        $admin = $this->admin();

        $this->assertTrue(Gate::forUser($admin)->allows('user.manage'));
    }

    public function test_permission_middleware_blocks_editor_from_user_management(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('users.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('users.create'))->assertForbidden();
    }

    public function test_role_manage_permission_blocks_user_without_it(): void
    {
        $editor = $this->editor();

        // editor 只被授予 dashboard.view + post.manage，角色管理按 role.manage 权限拦截
        $this->actingAs($editor)->get(route('roles.index'))->assertForbidden();
    }

    public function test_assigning_permission_to_editor_role_grants_access(): void
    {
        $editor = $this->editor();
        $editorRole = Role::query()->where('name', 'editor')->firstOrFail();
        $userPerm = Permission::query()->where('name', 'user.manage')->firstOrFail();

        // 人为授予 editor 角色 user.manage 权限
        $editorRole->givePermissionTo($userPerm);

        $this->assertTrue($editor->fresh()->can('user.manage'));
        $this->actingAs($editor->fresh())->get(route('users.index'))->assertOk();
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('dashboard.view'));
    }

    public function test_role_names_helper_returns_role_names(): void
    {
        $admin = $this->admin();
        $this->assertContains('admin', $admin->roleNames());
    }
}
