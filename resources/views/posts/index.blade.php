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

    <div class="card" x-data="listSelection()">
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

        {{-- 批量操作条 --}}
        <div class="px-5 pt-4">
            <x-bulk-actions
                :action-url="route('posts.bulk-delete')"
                method="POST"
                confirm-title="确定删除选中的文章吗？"
                confirm-message="删除后将进入回收站（软删除），可在回收站中还原。"
            >
                <button type="submit" class="btn-danger-ghost" @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))">
                    <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                    批量删除
                </button>
            </x-bulk-actions>
        </div>

        {{-- 文章表格 --}}
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'title', 'label' => '标题', 'sortable' => true],
                ['key' => null, 'label' => '作者'],
                ['key' => 'status', 'label' => '状态', 'sortable' => true],
                ['key' => 'published_at', 'label' => '发布时间', 'sortable' => true],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :selectable="true"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($posts as $post)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td w-10">
                            <input type="checkbox" value="{{ $post->id }}" data-select-row x-model="selectedIds" @change="syncSelectAll" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500" aria-label="选择文章 {{ $post->title }}">
                        </td>
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
                        <td class="td text-gray-600 dark:text-gray-300">{{ $post->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                {{-- 状态切换 --}}
                                <form method="POST" action="{{ route('posts.toggle-status', $post) }}" class="inline"
                                      data-confirm-title="确定要{{ $post->isPublished() ? '下线' : '发布' }}文章「{{ $post->title }}」吗？"
                                      data-confirm-message="{{ $post->isPublished() ? '下线后文章将转为草稿，不再对外展示。' : '发布后文章将对读者可见。' }}">
                                    @csrf
                                    @method('PATCH')
                                    <x-icon-button :icon="$post->isPublished() ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'"
                                                   :title="$post->isPublished() ? '下线' : '发布'"
                                                   :variant="$post->isPublished() ? 'ghost' : 'primary'"
                                                   @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                </form>
                                <x-icon-button icon="heroicon-o-pencil-square" :href="route('posts.edit', $post)" title="编辑" variant="primary" />
                                <form method="POST" action="{{ route('posts.destroy', $post) }}" class="inline"
                                      data-confirm-title="确定要删除文章「{{ $post->title }}」吗？"
                                      data-confirm-message="删除后将进入回收站（软删除），可在回收站中还原。">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="heroicon-o-trash" title="删除" variant="danger"
                                                   @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-document-text" title="没有找到文章" :colspan="7" />
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

    <x-confirm-modal />
</x-app-layout>
