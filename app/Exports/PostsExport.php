<?php

namespace App\Exports;

use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * 文章数据导出
 *
 * 使用 FromQuery + chunk 流式读取：导出与列表页「当前搜索/状态筛选」同源，
 * 大表不整表加载进内存。
 */
class PostsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected ?string $keyword = null, protected ?string $status = null)
    {
        //
    }

    public function headings(): array
    {
        return ['ID', '标题', '作者', '状态', '发布时间'];
    }

    public function query(): Builder
    {
        return Post::query()
            ->with('user:id,name')
            ->search($this->keyword)
            ->ofStatus($this->status)
            ->latest('id');
    }

    public function map($post): array
    {
        return [
            $post->id,
            $post->title,
            $post->user->name ?? '',
            $post->status === Post::STATUS_PUBLISHED ? '已发布' : '草稿',
            $post->published_at?->format('Y-m-d H:i'),
        ];
    }
}
