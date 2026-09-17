<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 操作日志模型：登录审计 + 后台写操作审计
 */
class OperationLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

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

    /** 操作日志对应的用户（软删除用户仍可关联） */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
