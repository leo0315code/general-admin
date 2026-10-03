<?php

namespace Tests\Feature;

use App\Models\OperationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * 操作日志治理：详情页 + 导出 + model:prune 清理
 *
 * 关注四件事：
 * 1. 详情页能展示列表页没露出的审计字段（User-Agent / 方法 / 模块）；
 * 2. 详情与导出都受权限控制（详情跟 log.manage，导出要 log.export）；
 * 3. 导出跟随列表筛选条件、文件能正常交付；
 * 4. model:prune 只清超期日志，保留期内不动，且保留天数可配置。
 */
class LogsGovernanceTest extends TestCase
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

    /** 无日志权限的用户（editor 角色） */
    private function editor(): User
    {
        return User::factory()->create()->assignRole(User::ROLE_EDITOR);
    }

    private function makeLog(array $attrs = []): OperationLog
    {
        return OperationLog::query()->create(array_merge([
            'user_id' => null,
            'username' => 'tester',
            'method' => 'POST',
            'module' => 'users',
            'action' => '创建',
            'description' => '创建用户 张三',
            'ip' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/537.36',
        ], $attrs));
    }

    public function test_show_displays_full_audit_fields(): void
    {
        $log = $this->makeLog();

        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.show', $log))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Mozilla/5.0', $html);
        $this->assertStringContainsString('>POST<', $html);
        $this->assertStringContainsString('创建用户 张三', $html);
        $this->assertStringContainsString('127.0.0.1', $html);
        $this->assertStringContainsString('users', $html);
    }

    public function test_show_requires_log_manage_permission(): void
    {
        $log = $this->makeLog();

        $this->actingAs($this->editor())
            ->get(route('logs.show', $log))
            ->assertForbidden();
    }

    public function test_export_requires_log_export_permission(): void
    {
        $this->actingAs($this->editor())
            ->get(route('logs.export'))
            ->assertForbidden();
    }

    public function test_export_downloads_excel(): void
    {
        Excel::fake();
        Excel::matchByRegex();
        $this->makeLog();

        $this->actingAs($this->admin())
            ->get(route('logs.export'))
            ->assertOk();

        Excel::assertDownloaded('/^操作日志-\d{14}\.xlsx$/');
    }

    public function test_export_accepts_filters(): void
    {
        Excel::fake();
        $this->makeLog();

        $this->actingAs($this->admin())
            ->get(route('logs.export', ['search' => '张三', 'action' => '创建', 'date' => '2026-09-29']))
            ->assertOk();
    }

    public function test_model_prune_removes_expired_keeps_fresh(): void
    {
        $old = $this->makeLog();
        $fresh = $this->makeLog();

        // created_at 不在 fillable，create 会被静默忽略，须用 query builder 改
        OperationLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(95)]);
        OperationLog::query()->whereKey($fresh->id)->update(['created_at' => now()->subDay()]);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertDatabaseMissing('operation_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('operation_logs', ['id' => $fresh->id]);
    }

    public function test_prune_respects_configured_retention(): void
    {
        // 保留天数调到 5：10 天前的日志也该被清掉
        config(['app.operation_log_retention_days' => 5]);

        $old = $this->makeLog();
        OperationLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(10)]);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertDatabaseMissing('operation_logs', ['id' => $old->id]);
    }

    public function test_index_renders_detail_link(): void
    {
        $html = (string) $this->actingAs($this->admin())
            ->get(route('logs.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('logs.show', ['log' => '__ID__']), $html);
    }
}
