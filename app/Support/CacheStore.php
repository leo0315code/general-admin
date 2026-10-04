<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Predis\Client;
use Throwable;

/**
 * 默认缓存 store 的可用性判定
 *
 * 为什么要单独判：登录限流（`RateLimiter`）与验证码计数都落在默认缓存上，
 * 而缓存配错的表现极其隐蔽——
 *   - array / null：每个 php-fpm 进程各算一份计数，限流上限被放大到「进程数 × N」，
 *     暴力破解基本畅通，且日志里没有任何报错；
 *   - redis 连不上：限流每次读写直接抛异常，登录页 500，但错误信息常被当成
 *     「数据库问题」排查半天。
 * 这两类都必须在上线前被机器拦下来，所以把判定集中在这里，供 `deploy:check` 复用。
 */
class CacheStore
{
    /** 这类驱动不跨进程共享，限流计数形同虚设 */
    private const UNSHARED = ['array', 'null'];

    public static function name(): string
    {
        return (string) config('cache.default');
    }

    /** 该驱动能否承担「跨进程共享」的缓存语义 */
    public static function usable(): bool
    {
        return ! in_array(self::name(), self::UNSHARED, true);
    }

    /** PHP 侧是否具备 redis 客户端实现（phpredis 扩展与 predis 包二选一） */
    public static function redisClientAvailable(): bool
    {
        return extension_loaded('redis') || class_exists(Client::class);
    }

    /**
     * 连通性探测：能读能写返回 null，否则返回给人看的错误说明
     *
     * 只探测缓存实际使用的那个连接（默认 `cache`，DB 1），而不是 `default`——
     * 两者 host/port/password 都取自同一组 REDIS_*，但库号不同，
     * 探错连接会出现「自检通过、运行时报错」。
     */
    public static function pingFailure(): ?string
    {
        $connection = (string) config('cache.stores.redis.connection', 'cache');
        $target = sprintf(
            '%s:%s',
            (string) env('REDIS_HOST', '127.0.0.1'),
            (string) env('REDIS_PORT', '6379'),
        );

        try {
            Redis::connection($connection)->command('ping');
        } catch (Throwable $e) {
            // 认证失败 / 连接被拒 / 超时，都归为同一类：配错就是不可用
            return sprintf(
                '缓存 store 为 redis，但连不上 %s（连接 %s）：%s',
                $target,
                $connection,
                Str::limit(trim($e->getMessage()), 120),
            );
        }

        return null;
    }

    /**
     * 不可用 / 不推荐的原因；返回 null 表示没问题
     *
     * 分三档，调用方据此决定阻断还是建议：
     *   1. 不能用（array/null、redis 无客户端、redis 连不通）→ 阻断；
     *   2. 能用但代价高（database）→ 建议；
     *   3. 正常（redis 通、file 等）→ 无问题。
     */
    public static function problem(): ?string
    {
        $name = self::name();

        if (! self::usable()) {
            return "缓存 store 为 {$name}，进程间不共享：登录限流与验证码计数每个进程各算一份，等于没限流；请在 .env 设置 CACHE_STORE=redis";
        }

        if ($name === 'redis') {
            if (! self::redisClientAvailable()) {
                return '缓存 store 为 redis，但 PHP 既没有 phpredis 扩展也没有 predis 包；限流与验证码读写会直接抛异常，登录页 500';
            }

            return self::pingFailure();
        }

        if ($name === 'database') {
            return '缓存 store 为 database：可用但代价高（限流计数每次 INSERT+UPDATE，且过期行不会自动清理，cache 表只增不减）；建议改 redis';
        }

        return null;
    }
}
