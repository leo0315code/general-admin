<?php

namespace App\Exports;

use App\Models\Post;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * 文章数据导出
 */
class PostsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['ID', '标题', '作者', '状态', '发布时间'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return Post::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Post $post) => [
                $post->id,
                $post->title,
                $post->user->name ?? '',
                $post->status === Post::STATUS_PUBLISHED ? '已发布' : '草稿',
                $post->published_at?->format('Y-m-d H:i'),
            ]);
    }
}
