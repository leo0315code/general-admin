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

    @php
        // 字典项页 Vue 组件 props（表格由 Vue 渲染，分页保留 Blade）
        $dictItemsIndexProps = [
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'value' => $item->value,
                'sort' => $item->sort,
                'status' => (bool) $item->status,
                'remark' => $item->remark,
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'itemsBase' => rtrim(route('dict-items.index'), '/'),
        ];
    @endphp

    <div class="card">
        {{-- 字典项表格（Vue 组件 DictItemsIndex） --}}
        <div
            data-vue-app
            data-component="dict-items-index"
            data-props='{!! vue_props($dictItemsIndexProps) !!}'
            x-ignore
        ></div>

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$items" />
                <x-pagination :paginator="$items" />
            </div>
        </div>
    </div>
</x-app-layout>
