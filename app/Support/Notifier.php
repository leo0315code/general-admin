<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\NotificationBroadcast;
use App\Models\User;

/**
 * 站内通知发送器（通知中心的唯一直达入口）
 *
 * 业务代码一律通过本类发消息，不直接 create 模型：
 * 统一了类型常量、字段顺序与「发给谁」的解析逻辑，
 * 后续若要加邮件/队列/去重，只需改这一类。
 */
class Notifier
{
    /**
     * 发送一条站内通知
     *
     * @param  User|int  $user  接收用户（模型或 ID）
     * @param  string  $type  事件类型，用 Notification::TYPE_* 常量
     * @param  string  $title  标题
     * @param  string|null  $content  正文
     * @param  string|null  $link  站内跳转地址（仅允许站内相对路径）
     */
    public static function send(
        User|int $user,
        string $type,
        string $title,
        ?string $content = null,
        ?string $link = null,
    ): Notification {
        $userId = $user instanceof User ? $user->getKey() : $user;

        $notification = Notification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'link' => self::safeLink($link),
        ]);

        // 新通知要立刻反映在顶栏未读徽章上，不能等缓存自然过期
        Notification::forgetUnreadCount($userId);

        // 实时推送（未启用 WS 时内部直接返回 false，不影响这里）
        Broadcaster::notification($notification);

        return $notification;
    }

    /**
     * 群发同一条通知
     *
     * @param  iterable<User|int>  $users
     * @return list<Notification>
     */
    public static function sendMany(
        iterable $users,
        string $type,
        string $title,
        ?string $content = null,
        ?string $link = null,
    ): array {
        $notifications = [];

        foreach ($users as $user) {
            $notifications[] = self::send($user, $type, $title, $content, $link);
        }

        return $notifications;
    }

    /**
     * 群发：把一条广播记录展开成 N 条通知
     *
     * 与 sendMany 的区别：群发人数可能成百上千，逐条 create 会有 N 次写库 + N 次推送；
     * 这里改为分块批量 insert、集中失效未读缓存、一次推送给全部 uid。
     *
     * @param  iterable<User|int>  $users  接收人（调用方负责按范围解析）
     * @return int 实际写入条数
     */
    public static function dispatch(NotificationBroadcast $broadcast, iterable $users): int
    {
        $now = now();
        $userIds = [];
        $rows = [];

        foreach ($users as $user) {
            $userId = $user instanceof User ? $user->getKey() : (int) $user;
            $userIds[] = $userId;

            $rows[] = [
                'user_id' => $userId,
                'broadcast_id' => $broadcast->getKey(),
                'type' => $broadcast->type,
                'title' => $broadcast->title,
                'content' => $broadcast->content,
                'link' => $broadcast->link,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        // 分块写入：一次 insert 几千行会让 SQL 占位符超限
        $written = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            Notification::query()->insert($chunk);
            $written += count($chunk);
        }

        foreach ($userIds as $userId) {
            Notification::forgetUnreadCount($userId);
        }

        // 一次推送给全部接收人（未启用 WS 时内部直接返回 false）
        Broadcaster::broadcast($userIds, $broadcast->title, $broadcast->getKey());

        return $written;
    }

    /**
     * 撤回一次群发：删掉这批通知、失效缓存、再推一次让在线客户端刷新
     *
     * @return int 删除的通知条数
     */
    public static function revoke(NotificationBroadcast $broadcast): int
    {
        $userIds = Notification::query()
            ->where('broadcast_id', $broadcast->getKey())
            ->pluck('user_id')
            ->all();

        $deleted = Notification::query()
            ->where('broadcast_id', $broadcast->getKey())
            ->delete();

        $broadcast->forceFill(['revoked_at' => now()])->save();

        foreach ($userIds as $userId) {
            Notification::forgetUnreadCount($userId);
        }

        Broadcaster::broadcast($userIds, '有一条消息已被撤回', $broadcast->getKey());

        return $deleted;
    }

    /** 发给所有启用状态的超级管理员 */
    public static function toAdmins(
        string $type,
        string $title,
        ?string $content = null,
        ?string $link = null,
    ): array {
        $admins = User::query()
            ->role(User::ROLE_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->get();

        return self::sendMany($admins, $type, $title, $content, $link);
    }

    /**
     * 归一化跳转地址：只放行站内相对路径，避免通知里被塞入站外地址（开放重定向 / 钓鱼）
     *
     * 调用方通常直接传 route() 生成的绝对 URL，这里先剥成本站相对路径；
     * 站外地址一律丢弃（宁可不给链接，也不给一个钓鱼入口）。
     */
    public static function safeLink(?string $link): ?string
    {
        if (! is_string($link) || $link === '') {
            return null;
        }

        $appUrl = (string) config('app.url');

        if ($appUrl !== '' && str_starts_with($link, $appUrl)) {
            $path = (string) (parse_url($link, PHP_URL_PATH) ?: '');
            $query = parse_url($link, PHP_URL_QUERY);
            $link = '/'.ltrim($path, '/').($query ? '?'.$query : '');
        }

        // '//host' 是协议相对 URL（指向外站），必须拒绝
        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return null;
        }

        return $link;
    }
}
