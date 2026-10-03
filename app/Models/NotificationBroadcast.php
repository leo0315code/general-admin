<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 群发消息记录
 *
 * 一条记录 = 一次主动发送。接收人明细落在 notifications 表（broadcast_id 回指），
 * 因此「发给谁」始终有据可查，撤回也能精确命中。
 */
class NotificationBroadcast extends Model
{
    /** 发送范围常量 */
    public const SCOPE_USERS = 'users';

    public const SCOPE_ROLE = 'role';

    public const SCOPE_ALL = 'all';

    public const TYPE_CUSTOM = 'message.custom';

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'link',
        'scope',
        'role',
        'recipients_count',
        'revoked_at',
    ];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'recipients_count' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    /** 发送人 */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** 本次群发生成的通知 */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'broadcast_id');
    }

    /** 只查未撤回的 */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * 按范围解析接收人
     *
     * 停用账号不收消息：避免给离职/禁用用户推送。
     *
     * @param  list<int>  $userIds  scope=users 时指定
     * @return Collection<int, User>
     */
    public function resolveRecipients(array $userIds = []): Collection
    {
        $query = User::query()->where('status', User::STATUS_ACTIVE);

        return match ($this->scope) {
            self::SCOPE_ROLE => $query->role((string) $this->role)->get(),
            self::SCOPE_USERS => $query->whereIn('id', $userIds)->get(),
            default => $query->get(),
        };
    }
}
