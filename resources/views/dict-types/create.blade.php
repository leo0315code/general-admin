<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建字典类型" description="创建新的字典类型" />
    </x-slot>

    @php
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
    ];
@endphp

    <div class="card max-w-2xl">
        <x-vue-mount component="dict-type-form" :props="$dictTypeFormProps" />
    </div>
</x-app-layout>
