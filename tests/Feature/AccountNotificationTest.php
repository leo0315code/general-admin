<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountCredentials;
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
}
