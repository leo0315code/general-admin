<?php

namespace App\Support;

use Illuminate\Cache\Repository;
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
 * 票据只存标量（用户 ID），兼容 `serializable_classes=false`。
 *
 * 存储位置由 `websocket.ticket_store` 决定（留空跟随 CACHE_STORE）。唯一硬性
 * 要求是**跨进程共享**：票据在 php-fpm 进程签发、在常驻 Worker 进程消费，
 * 两者内存不互通，所以 array / null 驱动下票据永远兑不出来。
 */
class WsTicket
{
    /** 这类驱动不跨进程共享，票据兑不出来 */
    private const UNSHARED = ['array', 'null'];

    public static function issue(int $userId): string
    {
        $ticket = Str::random(40);

        self::store()->put(self::key($ticket), $userId, self::ttl());

        return $ticket;
    }

    /**
     * 消费票据换取用户 ID；无效 / 已用过返回 null
     *
     * 一次性靠 `add()` 占位，不用 `pull()`。原因实测如下：
     * `Illuminate\Cache\Repository::pull()` 的实现是 `tap(get(), fn => forget())`
     * —— 两条独立命令，且全部驱动（含 RedisStore、DatabaseStore）都没有覆写它。
     * 并发下两个连接都能 get 到同一个 uid，「消费即失效」并不成立。
     *
     * `add()` 则相反，各驱动都以「只允许一个赢」的语义实现：
     *   RedisStore     → Lua 脚本（EVAL 单线程执行）
     *   DatabaseStore  → insertOrIgnore 靠 key 唯一索引兜底
     *   FileStore      → flock 独占锁
     *   MemcachedStore → 原生 memcached add
     * 所以抢不到占位的一律视为「已兑现过」，直接拒绝。
     */
    public static function consume(string $ticket): ?int
    {
        if ($ticket === '' || ! preg_match('/^[A-Za-z0-9]{20,80}$/', $ticket)) {
            return null;
        }

        $store = self::store();
        $key = self::key($ticket);

        // 先取值再抢占位，顺序不能反：伪造/已消费的票据取不到值就直接返回，
        // 一个键都不写——否则拿随机串反复请求就能往缓存里灌满占位键。
        $userId = $store->get($key);

        if (! is_numeric($userId)) {
            return null;
        }

        // 占位键的 TTL 不能短于票据本身，否则票据还没过期占位先失效，又能重放了
        if (! $store->add(self::spentKey($ticket), 1, self::ttl())) {
            return null;
        }

        // 值已取出，立刻删，不必等 TTL（占位键负责挡住后续兑换）
        $store->forget($key);

        return (int) $userId;
    }

    /** 票据 store：未显式配置时跟随默认 cache store */
    public static function store(): Repository
    {
        return Cache::store(config('websocket.ticket_store'));
    }

    /** 当前票据 store 的名字（未配置时取默认驱动名） */
    public static function storeName(): string
    {
        return (string) (config('websocket.ticket_store') ?: config('cache.default'));
    }

    /** 该驱动能否用于跨进程传递票据 */
    public static function usable(): bool
    {
        return ! in_array(self::storeName(), self::UNSHARED, true);
    }

    /** 不可用时给出可执行的处理建议 */
    public static function problem(): ?string
    {
        $name = self::storeName();

        if (! self::usable()) {
            return "票据 store 为 {$name}，不跨进程共享，Worker 永远读不到票据；请在 .env 设置 WS_TICKET_STORE=redis（或 file）";
        }

        if ($name === 'database') {
            return '票据 store 为 database：可用但代价最高（每次建连一次 INSERT+DELETE，且常驻 Worker 持有 MySQL 长连接，会被 wait_timeout 断开）；建议改 redis 或 file';
        }

        return null;
    }

    private static function ttl(): int
    {
        return max(10, (int) config('websocket.ticket_ttl'));
    }

    private static function key(string $ticket): string
    {
        return 'ws.ticket.'.$ticket;
    }

    private static function spentKey(string $ticket): string
    {
        return 'ws.ticket.'.$ticket.':spent';
    }
}
