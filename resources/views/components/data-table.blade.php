@props([
    'columns' => [],        // [['key' => 'id', 'label' => 'ID', 'sortable' => false, 'align' => 'left'], ...]
    'selectable' => false,
    'rowKey' => 'id',
    'sort' => null,
    'sortDir' => 'desc',
    'empty' => null,        // 空态组件实例（可选，页面也可自行在 rows slot 内处理）
])

@php
    $query = request()->query();
@endphp

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900/50">
            <tr>
                @if ($selectable)
                    <th class="th w-10">
                        <input
                            type="checkbox"
                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                            :checked="selectAll"
                            @change="toggleAll($event)"
                            aria-label="全选"
                        >
                    </th>
                @endif

                @foreach ($columns as $column)
                    @php
                        $key = $column['key'] ?? null;
                        $label = $column['label'] ?? '';
                        $sortable = (bool) ($column['sortable'] ?? false);
                        $align = $column['align'] ?? 'left';
                        $active = $key !== null && $sort === $key;
                        $nextDir = $active && $sortDir === 'asc' ? 'desc' : 'asc';
                        $url = $sortable && $key !== null
                            ? url()->current().'?'.http_build_query(array_merge($query, ['sort' => $key, 'sort_dir' => $nextDir, 'page' => 1]))
                            : null;
                    @endphp
                    <th class="th {{ $align === 'right' ? 'text-right' : '' }}" @if ($active) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                        @if ($sortable && $url)
                            <a href="{{ $url }}" class="th-sortable inline-flex items-center gap-1 group">
                                {{ $label }}
                                @if ($active)
                                    <x-icon :name="$sortDir === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down'" class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                                @else
                                    <x-icon name="heroicon-o-chevron-up-down" class="h-3.5 w-3.5 opacity-0 group-hover:opacity-40" />
                                @endif
                            </a>
                        @else
                            {{ $label }}
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            {{ $rows }}

            @if ($empty)
                {{ $empty }}
            @endif
        </tbody>
    </table>
</div>
