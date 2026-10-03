<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountCredentials;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 账号凭据邮件通知回归：
 * - 新建账号 / 重置密码 两个场景均发送通知
 * - 邮箱为空的用户跳过发送（email 可为空，不能因发信中断业务）
 * - 邮件通道异常时后台操作仍然成功（发信失败不回滚业务）
 */
class AccountNotificationTest extends TestCase
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

    public function test_creating_user_sends_credentials_notification(): void
    {
        Notification::fake();

        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => '新同事',
                'email' => 'newcomer@example.com',
                'password' => 'Str0ngPass123',
                'password_confirmation' => 'Str0ngPass123',
                'roles' => [Role::query()->where('name', 'editor')->firstOrFail()->id],
            ])
            ->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'newcomer@example.com')->firstOrFail();

        Notification::assertSentTo($user, AccountCredentials::class);
    }

    public function test_resetting_password_sends_credentials_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('users.reset-password', $user), [
                'new_password' => 'brandnew123456',
                'new_password_confirmation' => 'brandnew123456',
            ])
            ->assertRedirect(route('users.edit', $user));

        Notification::assertSentTo(
            $user->fresh(),
            AccountCredentials::class,
            fn (AccountCredentials $notification): bool => true
        );
    }

    public function test_user_without_email_skips_notification(): void
    {
        Notification::fake();

        // email 可为空：不应尝试发送，也不应报错
        $this->actingAs($this->admin())
            ->post(route('users.store'), [
                'name' => '无邮箱用户',
                'email' => '',
                'password' => 'Str0ngPass123',
                'password_confirmation' => 'Str0ngPass123',
                'roles' => [Role::query()->where('name', 'editor')->firstOrFail()->id],
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_mail_failure_does_not_break_password_reset(): void
    {
        // 发送通道抛异常：密码仍应重置成功、页面仍跳转（发信失败不回滚业务）
        Notification::fake();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP unreachable'));

        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('users.reset-password', $user), [
                'new_password' => 'brandnew123456',
                'new_password_confirmation' => 'brandnew123456',
            ])
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHas('success');

        $this->assertTrue(app('hash')->check('brandnew123456', $user->fresh()->password));
    }

    /** 邮件必须走队列（防回归：有人误删 ShouldQueue 会退回同步 SMTP 阻塞主流程） */
    public function test_credentials_notification_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AccountCredentials('plain-password', 'created'));
    }

    /**
     * database 队列序列化往返安全（readonly 属性 + 序列化不炸）：
     * 入队后 jobs 表有记录 → queue:work 处理后任务清空。
     * 邮件本身经 phpunit 的 MAIL_MAILER=array 无害发送，不在此断言。
     */
    public function test_database_queue_roundtrip_serializes_safely(): void
    {
        config(['queue.default' => 'database']);

        $user = User::factory()->create(['email' => 'queued@example.com']);
        $user->notify(new AccountCredentials('plain-password', 'created'));

        // 已入队（jobs 表有记录；序列化成功即证明 readonly 属性安全）
        $this->assertSame(1, \DB::table('jobs')->count());

        // worker 消费一条后任务清空，序列化/反序列化链路无异常
        $this->artisan('queue:work', ['--once' => true])->assertSuccessful();

        $this->assertSame(0, \DB::table('jobs')->count());
    }
}
