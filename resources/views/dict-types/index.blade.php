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

    @php
        // 字典类型页 Vue 组件 props（表格由 Vue 渲染，分页保留 Blade）
        $dictTypesIndexProps = [
            'dictTypes' => $dictTypes->map(fn ($dt) => [
                'id' => $dt->id,
                'name' => $dt->name,
                'type' => $dt->type,
                'description' => $dt->description,
                'items_count' => $dt->items_count,
                'status' => (bool) $dt->status,
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'typesBase' => rtrim(route('dict-types.index'), '/'),
            'itemsBase' => rtrim(route('dict-items.index'), '/'),
        ];
    @endphp

    <div class="card">
        {{-- 字典类型表格（Vue 组件 DictTypesIndex） --}}
        <x-vue-mount component="dict-types-index" :props="$dictTypesIndexProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$dictTypes" />
                <x-pagination :paginator="$dictTypes" />
            </div>
        </div>
    </div>
</x-app-layout>
