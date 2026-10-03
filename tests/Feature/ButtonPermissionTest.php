<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 按钮级权限的服务端校验
 *
 * 视图用 @can 隐藏按钮只是「看不见」——若服务端不同等校验，
 * 直接构造请求即可越权（典型：仅有菜单查看权却能批量删用户、改角色权限提权）。
 *
 * 本测试固定「菜单级权限 ≠ 操作权」的语义：
 * 有 X.manage 只能看列表，写动作必须另有对应的按钮级权限。
 */
class ButtonPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * 构造只带指定权限的用户（无 admin 角色，不享受 Gate::before 放行）
     *
     * @param  list<string>  $permissions
     */
    private function viewer(array $permissions): User
    {
        $user = User::factory()->create();
        $user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    /**
     * 取出页面首个 Vue 挂载点的 props（data-props 属性值）
     *
     * @return array<string, mixed>
     */
    /**
     * 取指定组件的挂载 props（页面可能同时挂载多个组件，如顶栏铃铛 + 列表）
     *
     * @param  string  $component  组件名，如 dict-types-index
     */
    private function mountedProps(string $html, string $component): array
    {
        $pattern = '/data-component="'.preg_quote($component, '/').'" data-props=\'([^\']*)\'/';

        $this->assertMatchesRegularExpression($pattern, $html, "页面未渲染 {$component} 挂载点");

        preg_match($pattern, $html, $matches);

        return json_decode($matches[1], true);
    }

    public function test_role_create_requires_button_permission(): void
    {
        $viewer = $this->viewer(['role.manage']);

        $this->actingAs($viewer)->get(route('roles.create'))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('roles.store'), ['name' => 'sneaky'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'sneaky']);
    }

    public function test_role_update_requires_button_permission(): void
    {
        $role = Role::findOrCreate('temp-role');
        $viewer = $this->viewer(['role.manage']);

        $this->actingAs($viewer)->get(route('roles.edit', $role))->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('roles.update', $role), ['name' => 'temp-role', 'description' => '越权修改'])
            ->assertForbidden();
    }

    public function test_role_destroy_requires_button_permission(): void
    {
        $role = Role::findOrCreate('temp-role');
        $viewer = $this->viewer(['role.manage']);

        $this->actingAs($viewer)->delete(route('roles.destroy', $role))->assertForbidden();

        $this->assertDatabaseHas('roles', ['name' => 'temp-role']);
    }

    public function test_menu_write_requires_button_permission(): void
    {
        $menu = Menu::query()->where('permission_name', 'user.manage')->firstOrFail();
        $viewer = $this->viewer(['menu.manage']);

        $this->actingAs($viewer)->get(route('menus.create'))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('menus.store'), ['pid' => 0, 'type' => 'dir', 'title' => '越权目录'])
            ->assertForbidden();

        $this->actingAs($viewer)->delete(route('menus.destroy', $menu))->assertForbidden();

        $this->assertDatabaseHas('menus', ['permission_name' => 'user.manage']);
    }

    public function test_bulk_user_actions_require_button_permission(): void
    {
        $target = User::factory()->create();
        $viewer = $this->viewer(['user.manage']);

        // 批量删除属于「删除用户」按钮，而非仅「能看列表」
        $this->actingAs($viewer)
            ->post(route('users.bulk-delete'), ['ids' => [$target->id]])
            ->assertForbidden();

        // 启停属于「编辑用户」按钮
        $this->actingAs($viewer)
            ->post(route('users.bulk-toggle-status'), ['ids' => [$target->id]])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->patch(route('users.toggle-status', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => User::STATUS_ACTIVE]);
    }

    public function test_force_destroy_requires_button_permission(): void
    {
        $target = User::factory()->create();
        $target->delete();

        $viewer = $this->viewer(['user.manage']);

        $this->actingAs($viewer)
            ->delete(route('users.force-destroy', $target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_dict_write_requires_button_permission(): void
    {
        $viewer = $this->viewer(['dict.manage']);

        $this->actingAs($viewer)->get(route('dict-types.create'))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('dict-types.store'), ['name' => '越权字典', 'type' => 'sneaky_type'])
            ->assertForbidden();

        $this->assertDatabaseMissing('dict_types', ['type' => 'sneaky_type']);
    }

    public function test_settings_update_requires_button_permission(): void
    {
        $viewer = $this->viewer(['settings.manage']);

        $this->actingAs($viewer)
            ->put(route('settings.update'), ['site_name' => '越权改名', 'pagination' => 15])
            ->assertForbidden();

        $this->assertDatabaseMissing('settings', ['key' => 'site_name', 'value' => '越权改名']);
    }

    public function test_view_receives_permission_flags_matching_server_gate(): void
    {
        $viewer = $this->viewer(['dict.manage', 'settings.manage']);

        $dictHtml = (string) $this->actingAs($viewer)->get(route('dict-types.index'))->getContent();
        $dictProps = $this->mountedProps($dictHtml, 'dict-types-index');

        $this->assertFalse($dictProps['can']['update'], '无 dict.update 时行内编辑按钮应隐藏');
        $this->assertFalse($dictProps['can']['destroy'], '无 dict.destroy 时行内删除按钮应隐藏');
        $this->assertStringNotContainsString(
            route('dict-types.create'),
            $dictHtml,
            '无 dict.create 时不应渲染新建入口'
        );

        $settingsHtml = (string) $this->actingAs($viewer)->get(route('settings.index'))->getContent();
        $this->assertFalse(
            $this->mountedProps($settingsHtml, 'settings-form')['canUpdate'],
            '无 settings.update 时保存按钮应隐藏'
        );
    }

    public function test_post_write_requires_button_permission(): void
    {
        $viewer = $this->viewer(['post.manage']);
        // 作者本人：Policy（数据范围）放行，但按钮级权限不足，仍须被拦
        $post = Post::factory()->create(['user_id' => $viewer->id]);
        $originalStatus = $post->status;

        $this->actingAs($viewer)->get(route('posts.create'))->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('posts.store'), [
                'title' => '越权文章',
                'content' => '内容',
                'status' => Post::STATUS_DRAFT,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)->get(route('posts.edit', $post))->assertForbidden();

        $this->actingAs($viewer)
            ->put(route('posts.update', $post), ['title' => $post->title, 'content' => 'x', 'status' => Post::STATUS_DRAFT])
            ->assertForbidden();

        $this->actingAs($viewer)->patch(route('posts.toggle-status', $post))->assertForbidden();
        $this->actingAs($viewer)->delete(route('posts.destroy', $post))->assertForbidden();

        // 批量删除 = 删除按钮；导出 = 独立的 posts.export
        $this->actingAs($viewer)
            ->post(route('posts.bulk-delete'), ['ids' => [$post->id]])
            ->assertForbidden();

        $this->actingAs($viewer)->get(route('posts.export'))->assertForbidden();

        $this->assertSame($originalStatus, $post->fresh()->status, '未获编辑权限时状态不应变化');
        $this->assertFalse($post->fresh()->trashed(), '未获删除权限的文章不应被软删除');
        $this->assertDatabaseMissing('posts', ['title' => '越权文章']);
    }

    public function test_post_restore_and_force_destroy_require_button_permission(): void
    {
        $viewer = $this->viewer(['post.manage']);
        $trashed = Post::factory()->create(['user_id' => $viewer->id]);
        $trashed->delete();

        // 还原归「编辑」按钮，彻底删除归「删除」按钮
        $this->actingAs($viewer)
            ->patch(route('posts.restore', $trashed->id))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->delete(route('posts.force-destroy', $trashed->id))
            ->assertForbidden();

        $this->assertNotNull(
            Post::query()->onlyTrashed()->find($trashed->id),
            '无权限时回收站记录必须原样保留'
        );
    }

    public function test_post_view_receives_permission_flags_matching_server_gate(): void
    {
        $viewer = $this->viewer(['post.manage']);

        $html = (string) $this->actingAs($viewer)->get(route('posts.index'))->getContent();
        $props = $this->mountedProps($html, 'posts-index');

        $this->assertFalse($props['can']['create'], '无 posts.create 时不渲染新建入口');
        $this->assertFalse($props['can']['update'], '无 posts.update 时行内编辑/发布按钮应隐藏');
        $this->assertFalse($props['can']['destroy'], '无 posts.destroy 时行内删除按钮应隐藏');
        $this->assertStringNotContainsString(route('posts.create'), $html);
        $this->assertStringNotContainsString(route('posts.export'), $html, '无 posts.export 时应隐藏导出入口');

        $trashProps = $this->mountedProps(
            (string) $this->actingAs($viewer)->get(route('posts.trash'))->getContent(),
            'posts-trash'
        );

        $this->assertFalse($trashProps['can']['update'], '无 posts.update 时还原按钮应隐藏');
        $this->assertFalse($trashProps['can']['destroy'], '无 posts.destroy 时彻底删除按钮应隐藏');
    }

    public function test_post_button_permission_grants_access_when_granted(): void
    {
        $operator = $this->viewer(['post.manage', 'posts.create', 'posts.update', 'posts.destroy']);

        $this->actingAs($operator)
            ->post(route('posts.store'), [
                'title' => '运营文章',
                'content' => '内容',
                'status' => Post::STATUS_DRAFT,
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->where('title', '运营文章')->firstOrFail();

        $this->actingAs($operator)
            ->patch(route('posts.toggle-status', $post))
            ->assertRedirect(route('posts.index'));

        $this->actingAs($operator)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'));

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_button_permission_grants_access_when_granted(): void
    {
        $operator = $this->viewer(['role.manage', 'roles.create', 'roles.destroy']);

        $this->actingAs($operator)
            ->post(route('roles.store'), ['name' => 'operator-role', 'description' => '正向验证'])
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseHas('roles', ['name' => 'operator-role']);

        $this->actingAs($operator)
            ->delete(route('roles.destroy', Role::findByName('operator-role')))
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['name' => 'operator-role']);
    }
}
