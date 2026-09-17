<?php

namespace Tests\Feature;

use App\Models\DictType;
use App\Models\User;
use App\Support\Dict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 数据字典读取辅助 dict() 回归：
 * - options / label / 默认值 / 不存在类型
 * - 停用项不参与读取
 * - 后台增删改后缓存失效，新值立即可见
 */
class DictHelperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_options_returns_value_label_map(): void
    {
        $options = dict('post_status');

        $this->assertSame('草稿', $options['draft']);
        $this->assertSame('已发布', $options['published']);
    }

    public function test_label_returns_chinese_name(): void
    {
        $this->assertSame('草稿', dict('post_status', 'draft'));
    }

    public function test_label_falls_back_to_default(): void
    {
        $this->assertSame('未知状态', dict('post_status', 'no-such-value', '未知状态'));
    }

    public function test_unknown_type_returns_empty_options_and_null_label(): void
    {
        $this->assertTrue(dict('not_exists_type')->isEmpty());
        $this->assertNull(dict('not_exists_type', 'x'));
    }

    public function test_disabled_type_returns_empty(): void
    {
        // demo_disabled 类型整体停用 → 整组字典不可读
        $this->assertTrue(dict('demo_disabled')->isEmpty());
        $this->assertNull(dict('demo_disabled', 'b'));
    }

    public function test_disabled_item_is_excluded(): void
    {
        // 动态造一个「类型启用、某项停用」的场景
        $type = DictType::query()->where('type', 'common_status')->firstOrFail();
        $type->items()->create([
            'label' => '已停用项',
            'value' => 'disabled-tmp',
            'sort' => 99,
            'status' => false,
        ]);

        $this->assertNull(dict('common_status', 'disabled-tmp'));
    }

    public function test_backend_update_invalidates_cache(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $type = DictType::query()->where('type', 'post_status')->firstOrFail();
        $item = $type->items()->where('value', 'draft')->firstOrFail();

        // 预读缓存
        $this->assertSame('草稿', dict('post_status', 'draft'));

        // 后台改字典项名称（保持启用 status=1）→ flush → 新名立即可见
        $this->actingAs($admin)->put(route('dict-items.update', $item), [
            'dict_type_id' => $type->id,
            'label' => '草稿（已改名）',
            'value' => 'draft',
            'sort' => 0,
            'status' => 1,
        ])->assertRedirect();

        $this->assertSame('草稿（已改名）', dict('post_status', 'draft'));
    }

    public function test_cached_value_is_plain_array(): void
    {
        // config/cache.php 的 serializable_classes=false 禁止对象反序列化，
        // 缓存里必须存标量数组而非 Collection 对象，否则读取变 __PHP_Incomplete_Class
        dict('post_status');

        $this->assertIsArray(app('cache')->get('dict.post_status'));
    }

    public function test_flush_clears_all_dict_cache(): void
    {
        dict('post_status');
        dict('common_status');

        $this->assertTrue(Cache::has('dict.post_status'));
        $this->assertTrue(Cache::has('dict.common_status'));

        Dict::flush();

        $this->assertFalse(Cache::has('dict.post_status'));
        $this->assertFalse(Cache::has('dict.common_status'));
    }
}
