<?php

namespace Tests\Feature;

use App\Models\DictItem;
use App\Models\DictType;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 种子数据完整性回归：保证 migrate:fresh --seed 后系统处于可用基线。
 *
 * 这些断言不依赖具体条数上限，而是在"数据缺失/关联断裂"时失败，
 * 用于防止 Seeder 顺序被改坏或权限与菜单脱节（幽灵权限 / 悬空权限）。
 */
class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_seed_creates_expected_baseline_rows(): void
    {
        $this->assertSame(26, Menu::query()->count());
        $this->assertSame(2, Role::query()->count());
        $this->assertSame(14, User::query()->count());
        $this->assertSame(20, Post::query()->count());
        $this->assertSame(6, DictType::query()->count());
        $this->assertSame(16, DictItem::query()->count());
        $this->assertSame(3, Setting::query()->count());
    }

    public function test_every_menu_permission_name_exists_in_permissions_table(): void
    {
        $names = Menu::query()->whereNotNull('permission_name')->pluck('permission_name')->unique();

        $this->assertNotEmpty($names);

        foreach ($names as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }

        // 反向：权限表里不应有菜单树之外来源的权限（防止幽灵权限）
        $this->assertSame(
            $names->sort()->values()->all(),
            Permission::query()->pluck('name')->sort()->values()->all()
        );
    }

    public function test_button_and_menu_nodes_declare_permission_name(): void
    {
        $missing = Menu::query()
            ->whereIn('type', [Menu::TYPE_MENU, Menu::TYPE_BUTTON])
            ->where(fn ($q) => $q->whereNull('permission_name')->orWhere('permission_name', ''))
            ->pluck('title')
            ->all();

        $this->assertSame([], $missing, '菜单/按钮节点缺少权限标识：'.implode('、', $missing));
    }

    public function test_menu_nodes_reference_existing_routes(): void
    {
        foreach (Menu::query()->whereNotNull('route')->pluck('route') as $route) {
            $this->assertTrue(Route::has($route), "菜单配置的路由名不存在：{$route}");
        }
    }

    public function test_admin_has_all_permissions_and_editor_is_subset(): void
    {
        $total = Permission::query()->count();

        $this->assertSame($total, Role::findByName('admin')->permissions()->count());

        $editorPermissions = Role::findByName('editor')->permissions()->pluck('name')->all();
        $this->assertNotEmpty($editorPermissions);
        $this->assertLessThan($total, count($editorPermissions));

        // editor 的权限必须都是真实存在的权限
        foreach ($editorPermissions as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name]);
        }

        // editor 只应拿到只读/内容类权限，不应误授系统管理权限
        foreach (['role.manage', 'menu.manage', 'settings.manage', 'user.manage'] as $forbidden) {
            $this->assertNotContains($forbidden, $editorPermissions);
        }
    }

    public function test_posts_belong_to_seeded_authors(): void
    {
        $this->assertSame(0, Post::query()->whereNotIn('user_id', User::query()->pluck('id'))->count());
        $this->assertGreaterThanOrEqual(2, Post::query()->distinct()->count('user_id'));
    }

    public function test_dict_types_have_items_and_all_are_reachable(): void
    {
        $this->assertSame(0, DictItem::query()->whereNotIn('dict_type_id', DictType::query()->pluck('id'))->count());

        foreach (DictType::query()->withCount('items')->get() as $type) {
            $this->assertGreaterThan(0, $type->items_count, "字典类型「{$type->name}」没有任何字典项");
        }
    }

    public function test_settings_contain_required_keys(): void
    {
        foreach (['site_name', 'pagination', 'copyright'] as $key) {
            $this->assertDatabaseHas('settings', ['key' => $key]);
        }
    }

    public function test_app_timezone_is_asia_shanghai(): void
    {
        // P0-3：时区由 UTC 改为东八区，视图 format()/操作日志时间不再少 8 小时
        $this->assertSame('Asia/Shanghai', config('app.timezone'));
    }

    public function test_backend_pages_render_seeded_data(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        // 字典类型列表：能看到种子写入的类型
        $this->actingAs($admin)
            ->get(route('dict-types.index'))
            ->assertOk()
            ->assertSee('文章状态')
            ->assertSee('post_status');

        // 字典项列表（按类型过滤）：能看到该类型下的字典项
        $postStatus = DictType::query()->where('type', 'post_status')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('dict-items.index', ['dict_type_id' => $postStatus->id]))
            ->assertOk()
            ->assertSee('草稿')
            ->assertSee('draft');

        // 系统设置：回显种子值而非空白表单
        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('通用管理后台');
    }

    public function test_seeders_are_idempotent(): void
    {
        $before = [
            Menu::query()->count(),
            Permission::query()->count(),
            User::query()->count(),
            Post::query()->count(),
            DictType::query()->count(),
            DictItem::query()->count(),
            Setting::query()->count(),
        ];

        // 再跑一次总种子，数据量不应变化
        $this->seed();

        $this->assertSame($before, [
            Menu::query()->count(),
            Permission::query()->count(),
            User::query()->count(),
            Post::query()->count(),
            DictType::query()->count(),
            DictItem::query()->count(),
            Setting::query()->count(),
        ]);
    }
}
