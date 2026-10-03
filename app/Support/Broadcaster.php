<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use GatewayClient\Gateway;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WebSocket 推送器（GatewayWorker 的 GatewayClient 封装）
 *
 * 设计要点：
 * 1. **默认关闭**：WS_ENABLED=false 时所有推送调用直接返回 false，业务完全不受影响
 *    （页面退化为轮询未读数接口），这是「推送不可用也不能让应用挂掉」的底线；
 * 2. **失败静默**：Register 不可达 / 连接异常只记日志，不向上抛，避免通知发送失败
 *    把创建用户、重置密码这类主流程一起拖垮；
 * 3. 推送只是「提醒」，真实数据仍以数据库为准——客户端收到后会再拉一次未读数。
 */
class Broadcaster
{
    /** 推送是否已启用 */
    public static function enabled(): bool
    {
        return (bool) config('websocket.enabled');
    }

    /**
     * 向某个用户推送一条消息
     *
     * @param  array<string, mixed>  $payload  会被 JSON 编码后下发
     * @return bool 是否真正推送成功（未启用 / 失败均为 false）
     */
    public static function toUser(User|int $user, array $payload): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $uid = $user instanceof User ? $user->getKey() : $user;

        try {
            Gateway::$registerAddress = (string) config('websocket.register_address');
            Gateway::sendToUid($uid, json_encode($payload, JSON_UNESCAPED_UNICODE));

            return true;
        } catch (Throwable $e) {
            // 推送通道故障不应影响业务主流程
            Log::warning('WebSocket 推送失败', [
                'uid' => $uid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** 推送一条新通知（客户端收到后会刷新未读徽章） */
    public static function notification(Notification $notification): bool
    {
        return self::toUser($notification->user_id, [
            'type' => 'notification',
            'id' => $notification->getKey(),
            'title' => $notification->title,
            'unread' => Notification::unreadCountFor($notification->user_id),
            'at' => now()->toIso8601String(),
        ]);
    }

    /**
     * 群发推送：一次调用推给多人（GatewayClient 的 sendToUid 接受 uid 数组）
     *
     * 不带 unread —— 群发时逐个算未读数代价太高，客户端收到后会自己拉接口校正。
     *
     * @param  list<int>  $userIds
     * @return bool 是否真正推送成功
     */
    public static function toUsers(array $userIds, array $payload): bool
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if ($uids === [] || ! self::enabled()) {
            return false;
        }

        try {
            Gateway::$registerAddress = (string) config('websocket.register_address');
            Gateway::sendToUid($uids, json_encode($payload, JSON_UNESCAPED_UNICODE));

            return true;
        } catch (Throwable $e) {
            Log::warning('WebSocket 群发推送失败', [
                'uids' => count($uids),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * 推送一条群发通知 / 撤回信号
     *
     * @param  list<int>  $userIds
     */
    public static function broadcast(array $userIds, string $title, ?int $broadcastId = null): bool
    {
        return self::toUsers($userIds, array_filter([
            'type' => 'notification',
            'broadcast_id' => $broadcastId,
            'title' => $title,
            'at' => now()->toIso8601String(),
        ]));
    }
}
