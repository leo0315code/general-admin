<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 操作日志模型：登录审计 + 后台写操作审计
 *
 * 实现 Prunable：由 `php artisan model:prune`（建议每日调度）清理
 * 保留期之外的旧日志，防止表无限膨胀。保留天数可用常量调整。
 */
class OperationLog extends Model
{
    use HasFactory, Prunable;

    public const UPDATED_AT = null;

    /** 日志保留天数（超期由 model:prune 清理） */
    public const RETENTION_DAYS = 90;

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'user_id',
        'username',
        'method',
        'module',
        'action',
        'description',
        'ip',
        'user_agent',
    ];

    /** 可清理的模型查询（model:prune 会批量删除） */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /** 操作日志对应的用户（软删除用户仍可关联） */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
