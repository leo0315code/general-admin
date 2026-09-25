<?php

namespace Tests\Feature;

use App\Models\DictType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 新增功能集成测试：操作日志 / 系统设置 / 数据字典 / Excel 导入导出
 */
class SystemFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    // ---- 操作日志 ----

    public function test_write_operations_are_logged(): void
    {
        // 管理员创建一篇文章 → 中间件应写入操作日志
        $this->actingAs($this->admin)
            ->post(route('posts.store'), [
                'title' => '日志测试文章',
                'content' => '内容',
                'status' => 'draft',
                'published_at' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('operation_logs', [
            'user_id' => $this->admin->id,
            'action' => '创建',
            'description' => '创建文章',
        ]);
    }

    public function test_log_page_is_accessible_by_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('logs.index'))
            ->assertOk()
            ->assertSee('操作日志');
    }

    public function test_editor_cannot_access_log_page(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)->get(route('logs.index'))->assertForbidden();
    }

    // ---- 系统设置 ----

    public function test_settings_page_accessible_by_admin_and_saves(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('系统设置')
            // Vue 化后表单由 SettingsForm 渲染，字段以 props 形式下发
            ->assertSee('data-component="settings-form"', false)
            ->assertSee('site_name');

        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'site_name' => '新版站点',
                'pagination' => '20',
                'copyright' => '© 2026 测试公司',
            ])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => '新版站点']);
        $this->assertDatabaseHas('settings', ['key' => 'pagination', 'value' => '20']);
    }

    public function test_saving_settings_flushes_settings_cache(): void
    {
        // 测试环境 boot 时 settings 表尚未迁移，缓存未写入；先模拟「已有缓存」的生产场景
        Cache::rememberForever('app.settings', fn () => Setting::query()->pluck('value', 'key')->all());
        $this->assertTrue(Cache::has('app.settings'));

        $this->actingAs($this->admin)
            ->put(route('settings.update'), [
                'site_name' => '缓存失效测试',
                'pagination' => '20',
                'copyright' => '© 2026',
            ])
            ->assertRedirect();

        // 保存后缓存必须失效，确保新值立即全局生效
        $this->assertFalse(Cache::has('app.settings'));
    }

    public function test_editor_cannot_access_settings(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)->get(route('settings.index'))->assertForbidden();
    }

    // ---- 数据字典 ----

    public function test_dict_type_and_item_crud(): void
    {
        // 创建类型
        $this->actingAs($this->admin)
            ->post(route('dict-types.store'), [
                'name' => '订单状态',
                'type' => 'order_status',
                'description' => '订单状态字典',
                'status' => '1',
            ])
            ->assertRedirect(route('dict-types.index'));

        $typeId = DictType::query()->where('type', 'order_status')->value('id');

        // 创建字典项
        $this->actingAs($this->admin)
            ->post(route('dict-items.store', ['dict_type_id' => $typeId]), [
                'label' => '待付款',
                'value' => 'pending',
                'sort' => '1',
                'status' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dict_types', ['type' => 'order_status']);
        $this->assertDatabaseHas('dict_items', ['dict_type_id' => $typeId, 'value' => 'pending']);

        // 列表页可访问
        $this->actingAs($this->admin)->get(route('dict-types.index'))->assertOk();
        $this->actingAs($this->admin)
            ->get(route('dict-items.index', ['dict_type_id' => $typeId]))
            ->assertOk()
            ->assertSee('待付款');
    }

    public function test_editor_cannot_access_dict(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)->get(route('dict-types.index'))->assertForbidden();
    }

    // ---- Excel 导入导出 ----

    public function test_users_export_downloads_xlsx(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
    }

    public function test_posts_export_downloads_xlsx(): void
    {
        $response = $this->actingAs($this->admin)->get(route('posts.export'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
    }

    public function test_users_import_requires_file(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.import'))
            ->assertSessionHasErrors('file');
    }
}
