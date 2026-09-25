<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 用户管理功能测试（基于 spatie/laravel-permission）：
 * 列表、创建、编辑、删除（软删除）、分配角色、重置密码
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_admin_can_view_user_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('用户管理')
            ->assertSee('admin@example.com');
    }

    public function test_user_list_supports_keyword_search(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.index', ['search' => 'editor@example.com']))
            ->assertOk()
            ->assertSee('editor@example.com');
    }

    public function test_admin_can_create_user_with_roles(): void
    {
        $editorRole = Role::query()->where('name', 'editor')->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => '新用户',
                'email' => 'new@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'roles' => [$editorRole->id],
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['name' => '新用户', 'email' => 'new@example.com', 'deleted_at' => null]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => User::query()->where('email', 'new@example.com')->firstOrFail()->id,
            'role_id' => $editorRole->id,
            'model_type' => User::class,
        ]);
    }

    public function test_duplicate_email_is_rejected_on_create(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => '重复邮箱',
                'email' => 'admin@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_email_is_optional_on_create(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [
                'name' => '无邮箱用户',
                'email' => '',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['name' => '无邮箱用户', 'email' => null, 'deleted_at' => null]);
    }

    public function test_multiple_users_without_email_are_allowed(): void
    {
        foreach (['甲', '乙'] as $name) {
            $this->actingAs($this->admin)
                ->post(route('users.store'), [
                    'name' => $name,
                    'email' => null,
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, User::query()->whereNull('email')->count());
    }

    public function test_email_can_be_cleared_on_update(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $this->actingAs($this->admin)
            ->patch(route('users.update', $user), [
                'name' => $user->name,
                'email' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->email);
    }

    public function test_admin_can_update_user_and_roles(): void
    {
        $user = User::factory()->create();
        $editorRole = Role::query()->where('name', 'editor')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('users.update', $user), [
                'name' => '改名用户',
                'email' => $user->email,
                'roles' => [$editorRole->id],
            ])
            ->assertRedirect(route('users.edit', $user));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => '改名用户']);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'role_id' => $editorRole->id,
            'model_type' => User::class,
        ]);
    }

    public function test_admin_can_reset_user_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->actingAs($this->admin)
            ->post(route('users.reset-password', $user), [
                'new_password' => 'brand-new-password',
                'new_password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('users.edit', $user));

        $this->assertTrue(
            Hash::check('brand-new-password', $user->fresh()->password)
        );
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        // 软删除：记录仍在，但 deleted_at 有值
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $this->admin))
            ->assertRedirect(route('users.index'));

        $this->assertNotSoftDeleted('users', ['id' => $this->admin->id]);
    }

    public function test_editor_cannot_access_user_management(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)->get(route('users.index'))->assertForbidden();
    }

    public function test_guest_cannot_access_user_management(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }
}
