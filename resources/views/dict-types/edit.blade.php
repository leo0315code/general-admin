<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑字典类型：{{ $dictType->name }}" :back-url="route('dict-types.index')" :breadcrumbs="[['label' => '数据字典', 'url' => route('dict-types.index')], ['label' => '编辑字典类型']]">
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
    $dictTypeFormProps = [
        'mode' => 'edit',
        'action' => route('dict-types.update', $dictType),
        'method' => 'PUT',
        'csrf' => csrf_token(),
        'old' => [
            'name' => old('name', $dictType->name),
            'type' => old('type', $dictType->type),
            'description' => old('description', $dictType->description),
            'status' => old('status', (bool) $dictType->status),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('dict-types.index'),
        'destroyUrl' => route('dict-types.destroy', $dictType),
    ];

    // 本页站内相对路径：字典项新增/编辑/删除后回跳本页（服务端校验 /console/ 前缀）
    $selfPath = '/'.ltrim(request()->path(), '/');

    // 内嵌字典项区块 props：关闭排序链接（排序请到字典项列表页）
    $dictItemsProps = [
        'items' => $items->map(fn ($item) => [
            'id' => $item->id,
            'label' => $item->label,
            'value' => $item->value,
            'sort' => $item->sort,
            'status' => (bool) $item->status,
            'remark' => $item->remark,
        ])->values(),
        'sortable' => false,
        'itemsBase' => rtrim(route('dict-items.index'), '/'),
        // 行内编辑带上回跳参数，保存后回到本页（而非字典项列表页）
        'editQuery' => '?redirect_to='.$selfPath,
        'redirectTo' => $selfPath,
        'can' => [
            'update' => auth()->user()?->can('dict.update') ?? false,
            'destroy' => auth()->user()?->can('dict.destroy') ?? false,
        ],
    ];
@endphp

    <div class="card max-w-2xl">
        <x-vue-mount component="dict-type-form" :props="$dictTypeFormProps" />
    </div>

    {{-- 字典项：与类型同屏管理，新增/编辑后回到本页 --}}
    <div class="card max-w-4xl mt-6">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">字典项（{{ $items->count() }}）</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">该类型下的可选值，新增/编辑后返回本页</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('dict.create')
                    <a href="{{ route('dict-items.create', ['dict_type_id' => $dictType->id, 'redirect_to' => $selfPath]) }}" class="btn-primary">
                        <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                        新建字典项
                    </a>
                @endcan
                <a href="{{ route('dict-items.index', ['dict_type_id' => $dictType->id]) }}" class="btn-secondary">
                    <x-icon name="heroicon-o-list-bullet" class="h-4 w-4" />
                    字典项列表
                </a>
            </div>
        </div>

        <x-vue-mount component="dict-items-index" :props="$dictItemsProps" />
    </div>
</x-app-layout>
