<x-app-layout>
    <x-slot name="header">
        <x-page-header title="用户回收站" description="已删除用户可在此还原或彻底清除" :back-url="route('users.index')">
            <x-slot name="actions">
                <a href="{{ route('users.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回用户列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    <div class="card" x-data="listSelection()">
        {{-- 搜索栏 --}}
        <div class="card-header">
            <form method="GET" action="{{ route('users.trash') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="search" value="{{ $keyword }}" placeholder="搜索已删除的姓名或邮箱…" class="input pl-9">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">搜索</button>
                    @if ($keyword)
                        <a href="{{ route('users.trash') }}" class="btn-secondary">清除</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 已删除用户表格 --}}
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => '姓名', 'sortable' => true],
                ['key' => 'email', 'label' => '邮箱', 'sortable' => true],
                ['key' => null, 'label' => '角色'],
                ['key' => 'created_at', 'label' => '删除时间', 'sortable' => true],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :selectable="true"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td w-10">
                            <input type="checkbox" value="{{ $user->id }}" data-select-row x-model="selectedIds" @change="syncSelectAll" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500" aria-label="选择用户 {{ $user->name }}">
                        </td>
                        <td class="td text-gray-500 dark:text-gray-400">{{ $user->id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                        <td class="td">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {{ $user->roles->pluck('name')->join(' / ') ?: '无角色' }}
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $user->deleted_at->format('Y-m-d H:i') }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <form method="POST" action="{{ route('users.restore', $user->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <x-icon-button icon="heroicon-o-arrow-uturn-left" title="还原该用户" variant="primary" />
                                </form>
                                <form method="POST" action="{{ route('users.force-destroy', $user->id) }}" class="inline"
                                      data-confirm-title="彻底删除用户「{{ $user->name }}」？"
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
                    <x-empty-state icon="heroicon-o-trash" title="回收站是空的" :colspan="7" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$users" />
                <x-pagination :paginator="$users" />
            </div>
        </div>
    </div>
</x-app-layout>
