<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 菜单管理（菜单即权限）功能测试
 *
 * 覆盖：
 * - 菜单树 CRUD（目录 / 菜单 / 按钮三级）与校验
 * - 菜单与 spatie 权限表的自动同步（创建 / 改名 / 删除）
 * - 删除保护（有子节点、权限已分配角色）
 * - 侧边栏由菜单表动态生成，且与权限同步显隐
 */
class MenuManagementTest extends TestCase
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

    // ---- 列表与权限 ----

    public function test_admin_can_view_menu_tree(): void
    {
        $this->actingAs($this->admin())
            ->get(route('menus.index'))
            ->assertOk()
            ->assertSee('菜单管理')
            ->assertSee('系统管理')
            ->assertSee('user.manage')
            ->assertSee('users.create');
    }

    public function test_editor_cannot_access_menu_management(): void
    {
        $this->actingAs($this->editor())->get(route('menus.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('menus.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_open_create_and_edit_forms(): void
    {
        $menu = Menu::query()->where('permission_name', 'user.manage')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('menus.create'))
            ->assertOk()
            // 表单 Vue 化：标签由 JS 渲染，服务端 HTML 保留数据 props（类型选项 + 路由建议）
            ->assertSee('目录')
            ->assertSee('users.index');

        // 带 pid 预选父级（列表页「子节点」按钮）
        $this->actingAs($this->admin())
            ->get(route('menus.create', ['pid' => $menu->id]))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('menus.edit', $menu))
            ->assertOk()
            ->assertSee('编辑节点：用户管理')
            ->assertSee('user.manage');
    }

    public function test_role_forms_render_permission_tree(): void
    {
        $role = Role::query()->where('name', 'editor')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('roles.create'))
            ->assertOk()
            ->assertSee('菜单管理')
            ->assertSee('users.create');

        $this->actingAs($this->admin())
            ->get(route('roles.edit', $role))
            ->assertOk()
            ->assertSee('全选本组');
    }

    // ---- 创建：自动同步权限 ----

    public function test_creating_menu_auto_creates_permission(): void
    {
        $this->actingAs($this->admin())
            ->post(route('menus.store'), [
                'pid' => 0,
                'type' => Menu::TYPE_MENU,
                'title' => '报表中心',
                'permission_name' => 'report.view',
                'icon' => 'heroicon-o-chart-bar',
                'route' => 'dashboard',
                'sort' => 5,
                'status' => 1,
                'remark' => '查看报表',
            ])
            ->assertRedirect(route('menus.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menus', [
            'title' => '报表中心',
            'permission_name' => 'report.view',
            'pid' => 0,
        ]);

        // 菜单即权限：权限表同步落库，label 取菜单名
        $this->assertDatabaseHas('permissions', [
            'name' => 'report.view',
            'label' => '报表中心',
        ]);
    }

    public function test_button_node_can_be_created_under_menu(): void
    {
        $menu = Menu::query()->where('permission_name', 'user.manage')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('menus.store'), [
                'pid' => $menu->id,
                'type' => Menu::TYPE_BUTTON,
                'title' => '导出用户明细',
                'permission_name' => 'users.export-detail',
                'sort' => 70,
                'status' => 1,
            ])
            ->assertRedirect(route('menus.index'));

        $this->assertDatabaseHas('menus', [
            'pid' => $menu->id,
            'type' => Menu::TYPE_BUTTON,
            'permission_name' => 'users.export-detail',
        ]);
        $this->assertDatabaseHas('permissions', ['name' => 'users.export-detail']);
    }

    // ---- 校验 ----

    public function test_button_node_must_have_parent(): void
    {
        $this->actingAs($this->admin())
            ->post(route('menus.store'), [
                'pid' => 0,
                'type' => Menu::TYPE_BUTTON,
                'title' => '游离按钮',
                'permission_name' => 'orphan.button',
            ])
            ->assertSessionHasErrors('pid');
    }

    public function test_invalid_route_name_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('menus.store'), [
                'pid' => 0,
                'type' => Menu::TYPE_MENU,
                'title' => '不存在的路由',
                'permission_name' => 'bad.route',
                'route' => 'not.exists.route',
            ])
            ->assertSessionHasErrors('route');
    }

    public function test_duplicate_permission_name_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('menus.store'), [
                'pid' => 0,
                'type' => Menu::TYPE_MENU,
                'title' => '重复权限',
                'permission_name' => 'user.manage',
            ])
            ->assertSessionHasErrors('permission_name');
    }

    // ---- 更新：权限改名同步 ----

    public function test_updating_menu_syncs_permission_label(): void
    {
        $menu = Menu::query()->where('permission_name', 'log.manage')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('menus.update', $menu), [
                'pid' => $menu->pid,
                'type' => $menu->type,
                'title' => '操作审计日志',
                'permission_name' => 'log.manage',
                'icon' => $menu->icon,
                'route' => $menu->route,
                'sort' => $menu->sort,
                'status' => 1,
            ])
            ->assertRedirect(route('menus.index'));

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'title' => '操作审计日志']);
        $this->assertDatabaseHas('permissions', ['name' => 'log.manage', 'label' => '操作审计日志']);
    }

    public function test_renaming_permission_cleans_up_old_unused_permission(): void
    {
        // 新建一个未被任何角色使用的权限节点
        $this->actingAs($this->admin())->post(route('menus.store'), [
            'pid' => 0,
            'type' => Menu::TYPE_MENU,
            'title' => '报表中心',
            'permission_name' => 'report.view',
            'route' => 'dashboard',
            'sort' => 90,
            'status' => 1,
        ]);

        $menu = Menu::query()->where('permission_name', 'report.view')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('menus.update', $menu), [
                'pid' => $menu->pid,
                'type' => $menu->type,
                'title' => $menu->title,
                'permission_name' => 'report.list',
                'route' => $menu->route,
                'sort' => $menu->sort,
                'status' => 1,
            ])
            ->assertRedirect(route('menus.index'));

        $this->assertDatabaseHas('permissions', ['name' => 'report.list']);
        // 旧权限未被角色使用 → 自动清理，不留幽灵权限
        $this->assertDatabaseMissing('permissions', ['name' => 'report.view']);
    }

    public function test_renaming_assigned_permission_keeps_old_one_and_warns(): void
    {
        // log.manage 已分配给 admin 角色（Seeder 授予），改名时不应静默删除
        $menu = Menu::query()->where('permission_name', 'log.manage')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('menus.update', $menu), [
                'pid' => $menu->pid,
                'type' => $menu->type,
                'title' => $menu->title,
                'permission_name' => 'log.view',
                'route' => $menu->route,
                'sort' => $menu->sort,
                'status' => 1,
            ])
            ->assertRedirect(route('menus.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('permissions', ['name' => 'log.view']);
        $this->assertDatabaseHas('permissions', ['name' => 'log.manage']);
    }

    public function test_cannot_set_own_child_as_parent(): void
    {
        $dir = Menu::query()->where('title', '系统管理')->firstOrFail();
        $child = Menu::query()->where('pid', $dir->id)->firstOrFail();

        // 把父级设为自己的子节点 → 形成环，应被拒绝
        $this->actingAs($this->admin())
            ->patch(route('menus.update', $dir), [
                'pid' => $child->id,
                'type' => $dir->type,
                'title' => $dir->title,
                'sort' => $dir->sort,
                'status' => 1,
            ])
            ->assertSessionHasErrors('pid');
    }

    // ---- 删除保护 ----

    public function test_menu_with_children_cannot_be_deleted(): void
    {
        $dir = Menu::query()->where('title', '系统管理')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('menus.destroy', $dir))
            ->assertRedirect(route('menus.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('menus', ['id' => $dir->id]);
    }

    public function test_menu_with_assigned_permission_cannot_be_deleted(): void
    {
        $menu = Menu::query()->where('permission_name', 'log.manage')->firstOrFail();

        // 把该权限分配给 editor 角色（模拟已授权）
        Role::query()->where('name', 'editor')->firstOrFail()
            ->givePermissionTo(Permission::query()->where('name', 'log.manage')->firstOrFail());

        $this->actingAs($this->admin())
            ->delete(route('menus.destroy', $menu))
            ->assertRedirect(route('menus.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('menus', ['id' => $menu->id]);
    }

    public function test_deleting_menu_removes_its_permission(): void
    {
        $this->actingAs($this->admin())->post(route('menus.store'), [
            'pid' => 0,
            'type' => Menu::TYPE_MENU,
            'title' => '临时菜单',
            'permission_name' => 'temp.view',
            'route' => 'dashboard',
            'sort' => 99,
            'status' => 1,
        ]);

        $menu = Menu::query()->where('permission_name', 'temp.view')->firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('menus.destroy', $menu))
            ->assertRedirect(route('menus.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertDatabaseMissing('permissions', ['name' => 'temp.view']);
    }

    // ---- 状态与侧边栏联动 ----

    public function test_new_menu_appears_in_sidebar_and_hides_when_disabled(): void
    {
        $this->actingAs($this->admin())->post(route('menus.store'), [
            'pid' => 0,
            'type' => Menu::TYPE_MENU,
            'title' => '数据中心',
            'permission_name' => 'data.view',
            'route' => 'dashboard',
            'sort' => 1,
            'status' => 1,
        ]);

        // 启用：侧边栏出现（admin 拥有全部权限）
        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('数据中心');

        // 停用：侧边栏消失
        $menu = Menu::query()->where('permission_name', 'data.view')->firstOrFail();
        $this->actingAs($this->admin())
            ->patch(route('menus.toggle-status', $menu))
            ->assertRedirect(route('menus.index'));

        $this->assertFalse($menu->fresh()->status);

        $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('数据中心');
    }

    public function test_non_admin_only_sees_menus_granted_by_permission(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('文章管理');
        $response->assertDontSee('菜单管理');
        $response->assertDontSee('用户管理');
    }
}
