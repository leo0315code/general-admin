<?php

namespace Tests\Feature;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 操作日志审计回归：
 * - 写操作自动记录（已有能力，防回归）；
 * - GET 导出类请求也要留痕（此前被 GET 过滤吞掉，导出永远无日志）；
 * - 未登录用户不产生审计记录。
 */
class AuditLogTest extends TestCase
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

    public function test_write_operation_is_logged(): void
    {
        $this->actingAs($this->admin())->post(route('users.store'), [
            'name' => '审计测试员',
            'email' => 'audit-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'roles' => [],
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'module' => '用户',
            'action' => '创建',
            'username' => '管理员',
        ]);
    }

    public function test_get_export_is_logged(): void
    {
        $this->actingAs($this->admin())->get(route('users.export'))->assertOk();

        $this->assertDatabaseHas('operation_logs', [
            'module' => '用户',
            'action' => '导出',
            'method' => 'GET',
            'username' => '管理员',
        ]);
    }

    public function test_get_export_of_posts_is_logged(): void
    {
        $this->actingAs($this->admin())->get(route('posts.export'))->assertOk();

        $this->assertDatabaseHas('operation_logs', [
            'module' => '文章',
            'action' => '导出',
            'method' => 'GET',
        ]);
    }

    public function test_plain_get_is_not_logged(): void
    {
        $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();

        $this->assertSame(0, OperationLog::query()->count());
    }

    public function test_guest_export_is_not_logged(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('users.export'))->assertRedirect(route('login'));

        $this->assertSame(0, OperationLog::query()->count());
    }
}
