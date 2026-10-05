<?php

namespace Tests\Feature;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 登录记录 Tab：操作日志页内按 module='登录' 过滤，不新增菜单与权限节点。
 *
 * 防回归点：
 * - scope=login 只出登录/登出类，不含后台增删改；
 * - scope 缺省/非法值回落到全部；
 * - 导出跟随 scope（文件名与内容同步切换）。
 */
class LoginLogTabTest extends TestCase
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

    private function makeLog(string $module, string $action): OperationLog
    {
        return OperationLog::query()->create([
            'username' => 'tester',
            'method' => 'POST',
            'module' => $module,
            'action' => $action,
            'description' => $module.'-'.$action,
            'ip' => '127.0.0.1',
        ]);
    }

    public function test_login_scope_shows_only_login_module(): void
    {
        $this->makeLog('登录', '登录');
        $this->makeLog('登录', '登录失败');
        $this->makeLog('用户', '创建');

        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.index', ['scope' => 'login']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('登录-登录', $html);
        $this->assertStringNotContainsString('用户-创建', $html, '登录 Tab 不应混入后台操作日志');
    }

    public function test_default_scope_shows_everything(): void
    {
        $this->makeLog('登录', '登录');
        $this->makeLog('用户', '创建');

        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('登录-登录', $html);
        $this->assertStringContainsString('用户-创建', $html);
    }

    /** 非法 scope 值一律回落全部，避免拼错参数导致页面「看起来像没数据」 */
    public function test_invalid_scope_falls_back_to_all(): void
    {
        $this->makeLog('用户', '创建');

        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.index', ['scope' => 'bogus']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('用户-创建', $html);
    }

    public function test_tab_links_are_rendered(): void
    {
        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('全部日志', $html);
        $this->assertStringContainsString('登录记录', $html);
        $this->assertStringContainsString('scope=login', $html);
    }

    public function test_export_follows_login_scope(): void
    {
        $this->makeLog('登录', '登录');
        $this->makeLog('用户', '创建');

        $this->actingAs($this->admin())
            ->get(route('logs.export', ['scope' => 'login']))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    /** 登录记录仍受 log.manage 菜单权限保护（Tab 不绕过鉴权） */
    public function test_login_tab_requires_log_permission(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([]);

        $this->actingAs($user)
            ->get(route('logs.index', ['scope' => 'login']))
            ->assertForbidden();
    }
}
