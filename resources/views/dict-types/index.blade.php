<x-app-layout>
    <x-slot name="header">
        <x-page-header title="数据字典" description="管理字典类型与字典项，统一业务状态值">
            <x-slot name="actions">
                <a href="{{ route('dict-types.create') }}" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    新建类型
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => '类型名称', 'sortable' => true],
                ['key' => 'type', 'label' => '类型标识', 'sortable' => true],
                ['key' => null, 'label' => '描述'],
                ['key' => null, 'label' => '字典项'],
                ['key' => null, 'label' => '状态'],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($dictTypes as $dictType)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ $dictType->id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $dictType->name }}</td>
                        <td class="td">
                            <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-600 dark:text-gray-300">{{ $dictType->type }}</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ $dictType->description ?? '—' }}</td>
                        <td class="td">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-info-100 dark:bg-info-500/20 text-xs font-medium text-info-700 dark:text-info-300">{{ $dictType->items_count }}</span>
                        </td>
                        <td class="td">
                            @if ($dictType->status)
                                <x-status-badge type="success" icon="heroicon-o-check-circle">启用</x-status-badge>
                            @else
                                <x-status-badge type="neutral" icon="heroicon-o-no-symbol">停用</x-status-badge>
                            @endif
                        </td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <x-icon-button icon="heroicon-o-list-bullet" :href="route('dict-items.index', ['dict_type_id' => $dictType->id])" title="字典项" />
                                <x-icon-button icon="heroicon-o-pencil-square" :href="route('dict-types.edit', $dictType)" title="编辑" variant="primary" />
                                <form method="POST" action="{{ route('dict-types.destroy', $dictType) }}" class="inline"
                                      data-confirm-title="确定要删除类型「{{ $dictType->name }}」及其全部字典项吗？"
                                      data-confirm-message="该类型下的所有字典项将一并删除，此操作不可恢复。">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="heroicon-o-trash" title="删除" variant="danger"
                                                   @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-bookmark-square" title="暂无字典类型" :colspan="7" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$dictTypes" />
                <x-pagination :paginator="$dictTypes" />
            </div>
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
