<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建字典类型" description="创建新的字典类型" :breadcrumbs="[['label' => '数据字典', 'url' => route('dict-types.index')], ['label' => '新建字典类型']]" />
    </x-slot>

    @php
    // 校验失败时回填已填写的字典项行（空行不回填，避免刷出一堆空输入框）
    $oldItems = collect(is_array(old('items')) ? old('items') : [])
        ->filter(fn ($row) => is_array($row) && (trim((string) ($row['label'] ?? '')) !== '' || trim((string) ($row['value'] ?? '')) !== ''))
        ->map(fn ($row) => [
            'label' => (string) ($row['label'] ?? ''),
            'value' => (string) ($row['value'] ?? ''),
            'sort' => is_numeric($row['sort'] ?? null) ? (int) $row['sort'] : 0,
        ])
        ->values()
        ->all();

    $dictTypeFormProps = [
        'mode' => 'create',
        'action' => route('dict-types.store'),
        'method' => 'POST',
        'csrf' => csrf_token(),
        'old' => [
            'name' => old('name', ''),
            'type' => old('type', ''),
            'description' => old('description', ''),
            'status' => old('status', true),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('dict-types.index'),
        'initialItems' => $oldItems,
    ];
@endphp

    <div class="card max-w-3xl">
        <x-vue-mount component="dict-type-form" :props="$dictTypeFormProps" />
    </div>

    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
        提示：字典项可在创建时一并添加，也可在保存后的编辑页继续维护。
    </p>
</x-app-layout>
