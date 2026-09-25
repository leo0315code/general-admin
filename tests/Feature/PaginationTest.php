<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 分页组件测试
 *
 * 覆盖：
 * - 跳页 URL 只替换 page 参数（不误伤 per_page）
 * - 跳页控件使用原生 JS data 属性（不依赖 Alpine）
 */
class PaginationTest extends TestCase
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

    public function test_jump_url_only_replaces_page_placeholder(): void
    {
        User::factory()->count(30)->create();

        $html = $this->actingAs($this->admin())
            ->get(route('users.index', ['per_page' => 10, 'page' => 1]))
            ->assertOk()
            ->getContent();

        // 跳页 URL：per_page 保持原值，只有 page 被占位
        $this->assertMatchesRegularExpression(
            '/data-jump-url="[^"]*per_page=10&(amp;)?page=__PAGE__"/',
            $html,
            'per_page=10 不应被占位符替换'
        );

        // 全局只允许一个占位符（多个会导致 JS 替换不全）
        $this->assertSame(1, substr_count($html, '__PAGE__'), '跳页 URL 只能有一个 __PAGE__ 占位符');
    }

    public function test_pagination_renders_native_js_controls(): void
    {
        User::factory()->count(30)->create();

        $html = $this->actingAs($this->admin())
            ->get(route('users.index', ['per_page' => 10]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-pagination', $html);
        $this->assertStringContainsString('data-page-jump', $html);
        $this->assertStringContainsString('data-page-goto', $html);
        // Alpine 已移除：不得再出现 x-model / x-data
        $this->assertStringNotContainsString('x-model', $html);
        $this->assertStringNotContainsString('x-data', $html);
    }
}
