<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 账号凭据邮件通知
 *
 * 两个场景共用：
 * - created：管理员新建账号 → 告知初始密码
 * - reset  ：管理员重置密码 → 告知新密码（并强制下次登录改密）
 *
 * 设计取舍：
 * - **异步发送**（implements ShouldQueue）：SMTP 发信较慢，不阻塞后台操作主流程；
 *   依赖常驻 queue worker（见 docs/queue.md），未配置 worker 时邮件滞留队列。
 *   测试环境 QUEUE_CONNECTION=sync（phpunit.xml），notify 仍同步发送，行为不变。
 * - 密码明文进邮件是本场景的必要代价（否则用户无法登录），因此
 *   两个场景都要求/提示「首次登录后立即修改密码」。
 * - 发送失败由调用方 catch 兜底，不影响后台操作主流程；
 *   入队后失败由 queue worker 重试（retry_after，默认 90 秒）。
 */
class AccountCredentials extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $plainPassword,
        private readonly string $scene = 'created',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name');
        $loginUrl = route('login');
        $isReset = $this->scene === 'reset';

        return (new MailMessage)
            ->subject($isReset ? "【{$appName}】您的登录密码已被重置" : "【{$appName}】您的账号已创建")
            ->greeting("你好，{$notifiable->name}！")
            ->line($isReset
                ? '管理员已为你重置登录密码，请使用以下凭据登录。'
                : '管理员已为你创建了后台账号，请使用以下凭据登录。')
            ->line('登录账号：'.$notifiable->email)
            ->line('初始密码：'.$this->plainPassword)
            ->action('立即登录', $loginUrl)
            ->line('安全提示：请在登录后立即修改密码，并妥善保管，不要告知他人。')
            ->line('如果你并未申请该操作，请联系系统管理员。');
    }
}
