<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * WebSocket 连接票据
 *
 * 为什么要有票据：浏览器连的是独立端口的 WS（如 2346），握手时不会带上主站
 * 的会话 Cookie，服务端无法直接知道「这个连接是谁」。因此：
 *   1. 已登录用户向 HTTP 接口换一张一次性票据（60 秒有效）；
 *   2. WS 建连后把票据发回来；
 *   3. Worker 进程消费票据拿到 uid 并 bindUid。
 *
 * 票据只存标量（用户 ID），兼容 `serializable_classes=false`；
 * 且**消费即失效**（Cache::pull），避免被重放。
 */
class WsTicket
{
    public static function issue(int $userId): string
    {
        $ticket = Str::random(40);

        Cache::put(self::key($ticket), $userId, now()->addSeconds(max(10, (int) config('websocket.ticket_ttl'))));

        return $ticket;
    }

    /** 消费票据换取用户 ID；无效 / 已用过返回 null */
    public static function consume(string $ticket): ?int
    {
        if ($ticket === '' || ! preg_match('/^[A-Za-z0-9]{20,80}$/', $ticket)) {
            return null;
        }

        $userId = Cache::pull(self::key($ticket));

        return is_numeric($userId) ? (int) $userId : null;
    }

    private static function key(string $ticket): string
    {
        return 'ws.ticket.'.$ticket;
    }
}
