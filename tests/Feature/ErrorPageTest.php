<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 中文错误页回归：403/404/419/500 不再显示框架默认英文页
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_404_renders_chinese_page(): void
    {
        $this->get('/console/definitely-not-exist')
            ->assertNotFound()
            ->assertSee('404')
            ->assertSee('页面不存在');
    }

    public function test_403_renders_chinese_page(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)
            ->get(route('users.index'))
            ->assertForbidden()
            ->assertSee('403')
            ->assertSee('无权访问');
    }

    public function test_419_view_renders_chinese(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('419', $html);
        $this->assertStringContainsString('会话已过期', $html);
    }

    public function test_500_view_renders_chinese(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('服务器', $html);
    }

    public function test_429_view_renders_chinese(): void
    {
        $html = view('errors.429')->render();

        $this->assertStringContainsString('429', $html);
        $this->assertStringContainsString('请求过于频繁', $html);
    }
}
