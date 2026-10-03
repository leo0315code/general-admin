<?php

namespace Tests\Feature;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 面包屑导航：page-header 组件按 breadcrumbs 参数渲染「首页 / 父级 / 当前页」。
 *
 * 防回归：只验证渲染结构与关键链接，不逐页断言（页面数会变）。
 */
class BreadcrumbTest extends TestCase
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

    public function test_create_page_renders_breadcrumb_trail(): void
    {
        $html = (string) $this->actingAs($this->admin())
            ->get(route('users.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="面包屑"', $html);
        $this->assertStringContainsString('>首页</a>', $html);
        $this->assertStringContainsString('>用户管理</a>', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('>新建用户</span>', $html);
    }

    public function test_page_without_breadcrumbs_renders_none(): void
    {
        $html = (string) $this->actingAs($this->admin())
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('aria-label="面包屑"', $html);
    }

    public function test_log_detail_shows_parent_link(): void
    {
        $log = OperationLog::query()->create([
            'username' => 'tester',
            'method' => 'POST',
            'module' => 'users',
            'action' => '创建',
            'description' => '创建用户 张三',
            'ip' => '127.0.0.1',
        ]);

        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.show', $log))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('>操作日志</a>', $html);
        $this->assertStringContainsString('>日志详情</span>', $html);
    }
}
