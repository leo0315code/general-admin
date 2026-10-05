<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * 登录会话管理
 *
 * 为什么直接删 sessions 表而不是调 `Auth::logoutOtherDevices()`：
 * 后者只改 remember_token + 触发事件，真正的「下次请求失效」依赖
 * `AuthenticateSession` 中间件去比对会话里的密码摘要——而我们没挂这个中间件，
 * 调了等于没踢。当前就是 database 会话驱动，直接删行最干脆：
 * 删掉的会话下次请求查不到 payload，立即被登出。
 *
 * 只保留两条边界：
 * - 「当前会话」永不删除（否则操作者自己被登出，体验突兀）；
 * - 若未来切换为非 database 驱动（redis/file），本类方法静默退化为 no-op，
 *   因为此时 sessions 表不再承载会话，需要按驱动重写（先报问题再动手）。
 */
class Sessions
{
    /** 踢掉某用户的其它全部会话，保留当前这条；返回踢掉的条数 */
    public static function invalidateOthers(int $userId, ?string $currentSessionId): int
    {
        if (! self::databaseDriven()) {
            return 0;
        }

        return (int) DB::table('sessions')
            ->where('user_id', $userId)
            ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->delete();
    }

    /** 踢掉某用户除「当前会话 + 指定保留会话」外的全部会话（管理员操作用，无需保留） */
    public static function invalidateAll(int $userId): int
    {
        if (! self::databaseDriven()) {
            return 0;
        }

        return (int) DB::table('sessions')->where('user_id', $userId)->delete();
    }

    /** 踢掉指定的一条会话（按会话 id 精确删）；返回是否踢到 */
    public static function invalidate(int $userId, string $sessionId): bool
    {
        if (! self::databaseDriven()) {
            return false;
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /** 某用户的活跃会话列表（按最后活动时间倒序），供「登录设备」展示 */
    public static function forUser(int $userId, ?string $currentSessionId): array
    {
        if (! self::databaseDriven()) {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($row) use ($currentSessionId) {
                $agent = UserAgent::parse((string) ($row->user_agent ?? ''));

                return [
                    'id' => (string) $row->id,
                    'ip' => (string) ($row->ip_address ?? '—'),
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'last_activity' => (int) $row->last_activity,
                    'last_activity_human' => self::humanize((int) $row->last_activity),
                    'is_current' => $currentSessionId !== null && (string) $row->id === $currentSessionId,
                ];
            })
            ->all();
    }

    /** 当前是否 database 会话驱动（非 database 时 sessions 表无数据） */
    public static function databaseDriven(): bool
    {
        return config('session.driver') === 'database';
    }

    /** 时间戳 → 「x 分钟前」粗粒度文案 */
    private static function humanize(int $ts): string
    {
        $diff = time() - $ts;

        if ($diff < 60) {
            return '刚刚';
        }
        if ($diff < 3600) {
            return intdiv($diff, 60).' 分钟前';
        }
        if ($diff < 86400) {
            return intdiv($diff, 3600).' 小时前';
        }

        return intdiv($diff, 86400).' 天前';
    }
}
