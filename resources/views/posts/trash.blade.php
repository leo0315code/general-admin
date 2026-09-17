<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">文章回收站</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">已删除文章可在此还原或彻底清除</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('posts.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回文章列表
                </a>
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        {{-- 搜索栏 + 状态筛选 --}}
        <div class="card-header">
            <form method="GET" action="{{ route('posts.trash') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="search" value="{{ $keyword }}" placeholder="搜索已删除的标题…" class="input pl-9">
                </div>
                <select name="status" class="input sm:w-40">
                    <option value="">全部状态</option>
                    <option value="draft" @selected($status === 'draft')>草稿</option>
                    <option value="published" @selected($status === 'published')>已发布</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">筛选</button>
                    @if ($keyword || $status)
                        <a href="{{ route('posts.trash') }}" class="btn-secondary">清除</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 已删除文章表格 --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">标题</th>
                        <th class="th">作者</th>
                        <th class="th">状态</th>
                        <th class="th">删除时间</th>
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
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">已发布</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">草稿</span>
                                @endif
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $post->deleted_at->format('Y-m-d H:i') }}</td>
                            <td class="td text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('posts.restore', $post->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-ghost" title="还原该文章">
                                        <x-icon name="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                                        还原
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('posts.force-destroy', $post->id) }}" class="inline"
                                      onsubmit="return confirm('彻底删除文章「{{ $post->title }}」将无法恢复，确定继续吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger-ghost" title="彻底删除（不可恢复）">
                                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                        彻底删除
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-document-text" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">回收站是空的</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 分页 --}}
        <div class="p-4 border-t border-gray-100 dark:border-gray-700/60">
            {{ $posts->links() }}
        </div>
    </div>
</x-app-layout>
