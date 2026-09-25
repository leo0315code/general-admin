<x-app-layout>
    <x-slot name="header">
        <x-page-header title="文章回收站" description="已删除文章可在此还原或彻底清除" :back-url="route('posts.index')">
            <x-slot name="actions">
                <a href="{{ route('posts.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回文章列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 文章回收站 Vue 组件 props（搜索/筛选/表格由 Vue 渲染，分页保留 Blade）
        $postsTrashProps = [
            'keyword' => $keyword ?? '',
            'status' => $status ?? '',
            'posts' => $posts->map(fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'author' => $post->user->name ?? null,
                'is_published' => $post->isPublished(),
                'deleted_at' => $post->deleted_at->format('Y-m-d H:i'),
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'postBase' => rtrim(route('posts.index'), '/'),
        ];
    @endphp

    <div class="card">
        {{-- 搜索/筛选 + 表格（Vue 组件 PostsTrash） --}}
        <x-vue-mount component="posts-trash" :props="$postsTrashProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$posts" />
                <x-pagination :paginator="$posts" />
            </div>
        </div>
    </div>
</x-app-layout>
