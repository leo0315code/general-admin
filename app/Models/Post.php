<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 文章模型（示例 CRUD 模板）
 *
 * 状态：draft=草稿 / published=已发布；软删除。
 */
class Post extends Model
{
    use HasFactory, SoftDeletes;

    /** 草稿状态 */
    public const STATUS_DRAFT = 'draft';

    /** 已发布状态 */
    public const STATUS_PUBLISHED = 'published';

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'status',
        'published_at',
    ];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /** 作者（一对多反向） */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 已发布文章作用域 */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** 草稿文章作用域 */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /** 关键字搜索作用域（标题模糊匹配） */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        return $query->when($keyword, function (Builder $query, string $keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        });
    }

    /** 状态筛选作用域 */
    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        return $query->when(in_array($status, [self::STATUS_DRAFT, self::STATUS_PUBLISHED], true), function (Builder $query) use ($status) {
            $query->where('status', $status);
        });
    }

    /** 是否已发布 */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
