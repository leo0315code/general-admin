<?php

namespace App\Models;

use App\Support\Uploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 附件（上传基座）
 *
 * 文件本体在私有磁盘（默认 storage/app/private），下载必须走带鉴权的路由，
 * 因此**不要**暴露 path，也不要把文件放进 public 目录。
 */
class Attachment extends Model
{
    use HasFactory;

    /** 每页显示数量 */
    public const PER_PAGE = 15;

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'name',
        'extension',
        'mime',
        'size',
        'checksum',
    ];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** 上传者 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 按原始文件名模糊搜索 */
    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        if (! $keyword) {
            return $query;
        }

        return $query->where('name', 'like', '%'.$keyword.'%');
    }

    /** 可读体积 */
    public function humanSize(): string
    {
        return Uploader::humanSize((int) $this->size);
    }

    /** 是否为图片（列表可直接预览的判断依据） */
    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
