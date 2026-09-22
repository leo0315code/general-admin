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
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'title', 'label' => '标题', 'sortable' => true],
                ['key' => null, 'label' => '作者'],
                ['key' => 'status', 'label' => '状态', 'sortable' => true],
                ['key' => 'created_at', 'label' => '删除时间', 'sortable' => true],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($posts as $post)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ $post->id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ $post->title }}</td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $post->user->name ?? '—' }}</td>
                        <td class="td">
                            @if ($post->isPublished())
                                <x-status-badge type="success" icon="heroicon-o-check-circle">已发布</x-status-badge>
                            @else
                                <x-status-badge type="neutral" icon="heroicon-o-pencil-square">草稿</x-status-badge>
                            @endif
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $post->deleted_at->format('Y-m-d H:i') }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <form method="POST" action="{{ route('posts.restore', $post->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <x-icon-button icon="heroicon-o-arrow-uturn-left" title="还原该文章" variant="primary" />
                                </form>
                                <form method="POST" action="{{ route('posts.force-destroy', $post->id) }}" class="inline"
                                      data-confirm-title="彻底删除文章「{{ $post->title }}」？"
                                      data-confirm-message="彻底删除将无法恢复，确定继续吗？">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="heroicon-o-trash" title="彻底删除（不可恢复）" variant="danger"
                                                   @click.prevent="window.__ui.confirmModal.open($el.closest('form'))" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-document-text" title="回收站是空的" :colspan="6" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$posts" />
                <x-pagination :paginator="$posts" />
            </div>
        </div>
    </div>
</x-app-layout>
