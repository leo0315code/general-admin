<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P1 安全收口回归：PostPolicy 数据范围 + 最后一个 admin 保护
 */
class SecurityGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    private function editor(): User
    {
        return User::query()->where('email', 'editor@example.com')->firstOrFail();
    }

    // ---- PostPolicy：数据范围 ----

    public function test_editor_cannot_update_others_post(): void
    {
        $editor = $this->editor();
        $post = Post::query()->where('user_id', '!=', $editor->id)->firstOrFail();

        $this->actingAs($editor)->get(route('posts.edit', $post))->assertForbidden();
    }

    public function test_editor_cannot_delete_others_post(): void
    {
        $editor = $this->editor();
        $post = Post::query()->where('user_id', '!=', $editor->id)->firstOrFail();

        $this->actingAs($editor)->delete(route('posts.destroy', $post))->assertForbidden();

        $this->assertNotSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_editor_can_update_own_post(): void
    {
        $editor = $this->editor();
        $post = Post::factory()->create(['user_id' => $editor->id, 'status' => Post::STATUS_DRAFT]);

        $this->actingAs($editor)
            ->patch(route('posts.update', $post), [
                'title' => '编辑自己的文章',
                'content' => '内容更新',
                'status' => Post::STATUS_PUBLISHED,
            ])
            ->assertRedirect(route('posts.edit', $post));

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => '编辑自己的文章']);
    }

    public function test_admin_can_update_any_post(): void
    {
        $post = Post::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('posts.edit', $post))
            ->assertOk();
    }

    // ---- 最后一个 admin 保护 ----

    /** 构造：operator（非 admin 但有完整用户管理权限） + 目标 = 唯一启用 admin */
    private function lastAdminScenario(): array
    {
        // 让 admin@example.com 成为唯一启用 admin（user1 从 admin 降为 editor）
        $user1 = User::query()->where('email', 'user1@example.com')->firstOrFail();
        $user1->syncRoles(['editor']);

        $operator = User::factory()->create(['name' => '操作员', 'email' => 'operator@example.com']);
        $operator->assignRole('editor');
        // 用户管理菜单级 + 按钮级权限（非 admin，走 Gate 正常判定）
        $operator->givePermissionTo(['user.manage', 'users.update', 'users.destroy']);

        return [$operator, $this->admin()];
    }

    public function test_last_admin_cannot_be_disabled(): void
    {
        [$operator, $admin] = $this->lastAdminScenario();

        $this->actingAs($operator)
            ->patch(route('users.toggle-status', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 1]);
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        [$operator, $admin] = $this->lastAdminScenario();

        $this->actingAs($operator)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect();

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        [$operator, $admin] = $this->lastAdminScenario();
        $editorRole = Role::query()->where('name', 'editor')->firstOrFail();

        $this->actingAs($operator)
            ->patch(route('users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'roles' => [$editorRole->id],
            ])
            ->assertRedirect();

        // admin 角色未被移除
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_non_last_admin_can_be_disabled(): void
    {
        // seed 默认有两个启用 admin（admin + user1）：停用 user1 不触发保护
        $user1 = User::query()->where('email', 'user1@example.com')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('users.toggle-status', $user1))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user1->id, 'status' => 0]);
    }

    public function test_keeping_admin_role_is_allowed_for_last_admin(): void
    {
        [$operator, $admin] = $this->lastAdminScenario();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();

        // 保留 admin 角色 → 编辑允许（不误伤正常资料更新）
        $this->actingAs($operator)
            ->patch(route('users.update', $admin), [
                'name' => '管理员（改名）',
                'email' => $admin->email,
                'roles' => [$adminRole->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => '管理员（改名）']);
    }
}
