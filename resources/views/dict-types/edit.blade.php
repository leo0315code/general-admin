<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑字典类型：{{ $dictType->name }}" :back-url="route('dict-types.index')">
            <x-slot name="actions">
                <a href="{{ route('dict-types.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

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
@endphp

    <div class="card max-w-2xl">
        <x-vue-mount component="dict-type-form" :props="$dictTypeFormProps" />
    </div>
</x-app-layout>
