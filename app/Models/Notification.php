<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * 站内通知（通知中心）
 *
 * 每条通知归属一个用户；read_at 为 null 表示未读。
 * 注意：类名与 Illuminate\Support\Facades\Notification 同名，
 * 因此本模型**必须**通过 `use App\Models\Notification;` 显式引入后再使用，
 * 不要依赖根命名空间别名解析。
 */
class Notification extends Model
{
    use HasFactory;

    /** 每页显示数量 */
    public const PER_PAGE = 20;

    /** 顶栏未读数缓存时长（秒） */
    public const UNREAD_CACHE_TTL = 30;

    /** 事件类型常量（避免各调用方手写字符串） */
    public const TYPE_ACCOUNT_CREATED = 'account.created';

    public const TYPE_PASSWORD_RESET = 'account.password_reset';

    public const TYPE_USERS_IMPORTED = 'users.imported';

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'user_id',
        'broadcast_id',
        'type',
        'title',
        'content',
        'link',
        'read_at',
    ];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /** 接收用户 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 所属群发（系统事件通知为 null） */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(NotificationBroadcast::class, 'broadcast_id');
    }

    /** 只查未读 */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /** 只查某个用户的通知 */
    public function scopeForUser(Builder $query, int|User $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->id : $user);
    }

    /** 是否已读 */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * 未读数（带 30 秒缓存）
     *
     * 顶栏铃铛每个页面请求都要取一次未读数，未缓存时每请求多一条 count 查询。
     * 缓存只存标量整数，兼容 `serializable_classes=false`。
     * 写入侧（标记已读 / 删除 / 发新通知）必须调用 forgetUnreadCount() 主动失效。
     */
    public static function unreadCountFor(int $userId): int
    {
        return (int) Cache::remember(
            self::unreadCacheKey($userId),
            now()->addSeconds(self::UNREAD_CACHE_TTL),
            fn () => static::query()->forUser($userId)->unread()->count()
        );
    }

    /** 失效某个用户的未读数缓存 */
    public static function forgetUnreadCount(int $userId): void
    {
        Cache::forget(self::unreadCacheKey($userId));
    }

    public static function unreadCacheKey(int $userId): string
    {
        return "notifications.unread.{$userId}";
    }

    /** 标记为已读（已读则跳过写库） */
    public function markAsRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }
}
