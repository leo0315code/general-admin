<?php

namespace Tests\Feature;

use App\Models\DictItem;
use App\Models\DictType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 字典类型编辑页内嵌「字典项」区块
 *
 * 背景：类型与字典项是父子关系，之前编辑/新增类型时页面只有 4 个字段，
 * 看不到任何字典项入口，只能先记住类型 ID 再去字典项列表页操作。
 * 现在编辑页同屏展示字典项，并支持新增/编辑后回跳本页。
 */
class DictTypeItemsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * 取出页面所有 Vue 挂载点的 props（按渲染顺序）
     *
     * @return list<array<string, mixed>>
     */
    private function allMountedProps(string $html): array
    {
        preg_match_all('/data-props=\'([^\']*)\'/', $html, $matches);

        return array_map(fn ($raw) => json_decode($raw, true), $matches[1]);
    }

    /** 编辑页同屏渲染字典项区块，并带「新建字典项」入口 */
    public function test_edit_page_renders_items_panel(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $type = DictType::query()->firstOrFail();

        DictItem::query()->create([
            'dict_type_id' => $type->id,
            'label' => '待付款',
            'value' => 'pending',
            'sort' => 1,
            'status' => true,
        ]);

        $html = (string) $this->actingAs($admin)->get(route('dict-types.edit', $type))->getContent();

        $this->assertStringContainsString('字典项', $html);
        $this->assertStringContainsString('新建字典项', $html);

        $itemsProps = collect($this->allMountedProps($html))
            ->firstWhere(fn (array $props) => array_key_exists('items', $props));

        $this->assertNotNull($itemsProps, '编辑页未挂载字典项表格');
        $this->assertSame('待付款', $itemsProps['items'][0]['label']);
        $this->assertFalse($itemsProps['sortable'], '内嵌区块不应带排序链接');
        $this->assertStringContainsString('dict-types', $itemsProps['redirectTo']);
        // 行内编辑链接带回跳参数，保存后回到类型编辑页
        $this->assertStringContainsString('redirect_to', $itemsProps['editQuery']);
    }

    /** 无 dict.create 权限时不渲染「新建字典项」按钮（与服务端 Gate 一致） */
    public function test_create_entry_hidden_without_button_permission(): void
    {
        $user = User::factory()->create();
        $user->syncPermissions(['dict.manage', 'dict.update']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $type = DictType::query()->firstOrFail();
        $html = (string) $this->actingAs($user)->get(route('dict-types.edit', $type))->getContent();

        $this->assertStringNotContainsString('新建字典项', $html);
    }

    /** 新建类型后直达编辑页（该页即可补充字典项） */
    public function test_type_store_lands_on_edit_page(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('dict-types.store'), [
                'name' => '订单状态',
                'type' => 'order_status',
                'status' => '1',
            ])
            ->assertRedirect(route('dict-types.edit', DictType::query()->where('type', 'order_status')->firstOrFail()));
    }

    /** 新建类型时可同页批量添加字典项，整行留空的行直接丢弃 */
    public function test_type_store_creates_items_in_same_request(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('dict-types.store'), [
                'name' => '订单状态',
                'type' => 'order_status',
                'status' => '1',
                'items' => [
                    ['label' => '待付款', 'value' => 'pending', 'sort' => 0],
                    ['label' => '待发货', 'value' => 'shipping', 'sort' => 1],
                    ['label' => '', 'value' => ''],
                ],
            ])
            ->assertRedirect(route('dict-types.edit', DictType::query()->where('type', 'order_status')->firstOrFail()));

        $typeId = DictType::query()->where('type', 'order_status')->value('id');

        $this->assertSame(2, DictItem::query()->where('dict_type_id', $typeId)->count());
        $this->assertDatabaseHas('dict_items', ['dict_type_id' => $typeId, 'value' => 'shipping', 'label' => '待发货']);
    }

    /** 同批次字典项值重复 → 422，且类型本身不落库 */
    public function test_duplicate_item_values_are_rejected(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('dict-types.store'), [
                'name' => '重复值类型',
                'type' => 'dup_type',
                'items' => [
                    ['label' => '甲', 'value' => 'same'],
                    ['label' => '乙', 'value' => 'same'],
                ],
            ])
            ->assertSessionHasErrors('items.1.value');

        $this->assertDatabaseMissing('dict_types', ['type' => 'dup_type']);
    }

    /** 填了名称却没填值 → 行内必填提示 */
    public function test_item_value_is_required_when_row_filled(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('dict-types.store'), [
                'name' => '缺值类型',
                'type' => 'missing_value_type',
                'items' => [['label' => '只有名称', 'value' => '']],
            ])
            ->assertSessionHasErrors('items.0.value');
    }

    /** 列表页「字典项」列是入口链接（依赖 itemsBase 生成） */
    public function test_index_page_provides_items_base_for_column_link(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $html = (string) $this->actingAs($admin)->get(route('dict-types.index'))->getContent();
        $props = collect($this->allMountedProps($html))
            ->firstWhere(fn (array $p) => array_key_exists('dictTypes', $p));

        $this->assertNotNull($props);
        $this->assertStringContainsString('dict-items', $props['itemsBase']);
    }

    /** 从类型编辑页新增字典项后回跳编辑页 */
    public function test_item_store_redirects_back_to_type_edit_page(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $type = DictType::query()->firstOrFail();
        $back = '/'.trim((string) config('app.admin_prefix', 'console'), '/').'/dict-types/'.$type->id.'/edit';

        $this->actingAs($admin)
            ->post(route('dict-items.store', ['dict_type_id' => $type->id]), [
                'label' => '待发货',
                'value' => 'shipping',
                'sort' => 2,
                'status' => '1',
                'redirect_to' => $back,
            ])
            ->assertRedirect($back);

        $this->assertDatabaseHas('dict_items', ['dict_type_id' => $type->id, 'value' => 'shipping']);
    }

    /** 删除字典项同样回跳类型编辑页 */
    public function test_item_destroy_redirects_back_to_type_edit_page(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $type = DictType::query()->firstOrFail();
        $item = DictItem::query()->create([
            'dict_type_id' => $type->id,
            'label' => '已取消',
            'value' => 'canceled',
            'sort' => 3,
            'status' => true,
        ]);

        $back = '/'.trim((string) config('app.admin_prefix', 'console'), '/').'/dict-types/'.$type->id.'/edit';

        $this->actingAs($admin)
            ->delete(route('dict-items.destroy', $item), ['redirect_to' => $back])
            ->assertRedirect($back);
    }

    /** 站外/越权回跳地址一律忽略，防开放重定向 */
    public function test_unsafe_redirect_to_is_ignored(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $type = DictType::query()->firstOrFail();
        $fallback = route('dict-items.index', ['dict_type_id' => $type->id]);

        foreach (['https://evil.example.com/console/x', '//evil.example.com', '/admin/users', '/console/../users'] as $evil) {
            $this->actingAs($admin)
                ->post(route('dict-items.store', ['dict_type_id' => $type->id]), [
                    'label' => '异常项',
                    'value' => 'evil_'.md5($evil),
                    'redirect_to' => $evil,
                ])
                ->assertRedirect($fallback);
        }
    }
}
