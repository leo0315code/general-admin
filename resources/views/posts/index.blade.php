<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">文章管理</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">内容 CRUD 示例模板，可复制扩展为业务模块</p>
            </div>
            <div class="flex items-center gap-2">
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
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        {{-- 搜索栏 + 状态筛选 --}}
        <div class="card-header">
            <form method="GET" action="{{ route('posts.index') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="search" value="{{ $keyword }}" placeholder="搜索文章标题…" class="input pl-9">
                </div>
                <select name="status" class="input sm:w-40">
                    <option value="">全部状态</option>
                    <option value="draft" @selected($status === 'draft')>草稿</option>
                    <option value="published" @selected($status === 'published')>已发布</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">筛选</button>
                    @if ($keyword || $status)
                        <a href="{{ route('posts.index') }}" class="btn-secondary">清除</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 文章表格 --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">标题</th>
                        <th class="th">作者</th>
                        <th class="th">状态</th>
                        <th class="th">发布时间</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($posts as $post)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $post->id }}</td>
                            <td class="td font-medium text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ $post->title }}</td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $post->user->name ?? '—' }}</td>
                            <td class="td">
                                @if ($post->isPublished())
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                        <x-icon name="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                        已发布
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <x-icon name="heroicon-o-pencil-square" class="h-3.5 w-3.5" />
                                        草稿
                                    </span>
                                @endif
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $post->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="td text-right whitespace-nowrap">
                                {{-- 状态切换 --}}
                                <form method="POST" action="{{ route('posts.toggle-status', $post) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-sm font-medium transition {{ $post->isPublished() ? 'text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10' : 'text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-500/10' }}"
                                        onclick="return confirm('确定要{{ $post->isPublished() ? '下线' : '发布' }}文章「{{ $post->title }}」吗？');"
                                    >
                                        <x-icon :name="$post->isPublished() ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'" class="h-4 w-4" />
                                        {{ $post->isPublished() ? '下线' : '发布' }}
                                    </button>
                                </form>
                                <a href="{{ route('posts.edit', $post) }}" class="btn-ghost">
                                    <x-icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                    编辑
                                </a>
                                <form method="POST" action="{{ route('posts.destroy', $post) }}" class="inline" onsubmit="return confirm('确定要删除文章「{{ $post->title }}」吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger-ghost">
                                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                        删除
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-document-text" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">没有找到文章</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 分页 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $posts->links() }}
        </div>
    </div>
</x-app-layout>
