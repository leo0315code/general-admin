<x-app-layout>
    <x-slot name="header">
        <x-page-header title="文章管理" description="内容 CRUD 示例模板，可复制扩展为业务模块">
            <x-slot name="actions">
                <a href="{{ route('posts.export', request()->query()) }}" class="btn-secondary" title="导出当前搜索结果">
                    <x-icon name="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                    导出
                </a>
                <a href="{{ route('posts.trash') }}" class="btn-secondary relative" title="已删除文章（回收站）">
                    <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                    回收站
                    @if ($trashedCount > 0)
                        <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-xs font-semibold">{{ $trashedCount }}</span>
                    @endif
                </a>
                <a href="{{ route('posts.create') }}" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    新建文章
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 文章管理页 Vue 组件 props（列表页样板：搜索/状态筛选/勾选/批量/表格由 Vue 渲染）
        $postsIndexProps = [
            'keyword' => $keyword ?? '',
            'status' => $status ?? '',
            'posts' => $posts->map(fn ($post) => [
                'id' => $post->id,
                'title' => $post->title,
                'author' => $post->user->name ?? null,
                'is_published' => $post->isPublished(),
                'published_at' => $post->published_at?->format('Y-m-d H:i'),
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'postBase' => rtrim(route('posts.index'), '/'),
            'routes' => [
                'bulk_delete' => route('posts.bulk-delete'),
            ],
        ];
    @endphp

    <div class="card">
        {{-- 列表交互层：搜索 / 状态筛选 / 勾选 / 批量 / 表格（Vue 组件 PostsIndex） --}}
        <x-vue-mount component="posts-index" :props="$postsIndexProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$posts" />
                <x-pagination :paginator="$posts" />
            </div>
        </div>
    </div>
</x-app-layout>
