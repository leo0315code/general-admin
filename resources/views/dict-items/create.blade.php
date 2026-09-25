<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建字典项" description="字典类型：{{ $dictType->name }}" :back-url="route('dict-items.index', ['dict_type_id' => $dictType->id])">
            <x-slot name="actions">
                <a href="{{ route('dict-items.index', ['dict_type_id' => $dictType->id]) }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回字典项
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
    $dictItemFormProps = [
        'mode' => 'create',
        'action' => route('dict-items.store', ['dict_type_id' => $dictType->id]),
        'method' => 'POST',
        'csrf' => csrf_token(),
        'old' => [
            'label' => old('label', ''),
            'value' => old('value', ''),
            'sort' => old('sort', 0),
            'status' => old('status', true),
            'remark' => old('remark', ''),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('dict-items.index', ['dict_type_id' => $dictType->id]),
    ];
@endphp

    <div class="card max-w-2xl">
        <x-vue-mount component="dict-item-form" :props="$dictItemFormProps" />
    </div>
</x-app-layout>
