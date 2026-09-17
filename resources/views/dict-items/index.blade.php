<x-app-layout>
    <x-slot name="header">
        <x-page-header title="字典项：{{ $dictType->name }}" description="类型标识：{{ $dictType->type }}">
            <x-slot name="actions">
                <a href="{{ route('dict-types.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回类型
                </a>
                <a href="{{ route('dict-items.create', ['dict_type_id' => $dictType->id]) }}" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    新建字典项
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => null, 'label' => '名称'],
                ['key' => 'value', 'label' => '值', 'sortable' => true],
                ['key' => 'sort', 'label' => '排序', 'sortable' => true],
                ['key' => null, 'label' => '状态'],
                ['key' => null, 'label' => '备注'],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($items as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ $item->id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $item->label }}</td>
                        <td class="td">
                            <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-600 dark:text-gray-300">{{ $item->value }}</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $item->sort }}</td>
                        <td class="td">
                            @if ($item->status)
                                <x-status-badge type="success" icon="heroicon-o-check-circle">启用</x-status-badge>
                            @else
                                <x-status-badge type="neutral" icon="heroicon-o-no-symbol">停用</x-status-badge>
                            @endif
                        </td>
                        <td class="td text-gray-500 dark:text-gray-400 max-w-[180px] truncate">{{ $item->remark ?? '—' }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <x-icon-button icon="heroicon-o-pencil-square" :href="route('dict-items.edit', $item)" title="编辑" variant="primary" />
                                <form method="POST" action="{{ route('dict-items.destroy', $item) }}" class="inline"
                                      data-confirm-title="确定要删除字典项「{{ $item->label }}」吗？"
                                      data-confirm-message="删除后该字典项将无法恢复。">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="heroicon-o-trash" title="删除" variant="danger"
                                                   @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-list-bullet" title="该类型下暂无字典项" :colspan="7" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$items" />
                <x-pagination :paginator="$items" />
            </div>
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
