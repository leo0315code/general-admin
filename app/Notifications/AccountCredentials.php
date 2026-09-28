<?php

namespace App\Notifications;

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
 * - **同步发送**（未 implements ShouldQueue）：部署侧无需常驻 queue worker；
 *   如日后接入队列，改为 implements ShouldQueue 即可，调用方无需改动。
 *   当前 MAIL_MAILER=log 时仅写日志，配置 SMTP 后自动真实发信。
 * - 密码明文进邮件是本场景的必要代价（否则用户无法登录），因此
 *   两个场景都要求/提示「首次登录后立即修改密码」。
 * - 发送失败由调用方 catch 兜底，不影响后台操作主流程。
 */
class AccountCredentials extends Notification
{
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
