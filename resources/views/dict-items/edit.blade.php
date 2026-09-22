<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑字典项：{{ $dictItem->label }}" description="字典类型：{{ $dictItem->dictType->name }}" :back-url="route('dict-items.index', ['dict_type_id' => $dictItem->dict_type_id])">
            <x-slot name="actions">
                <a href="{{ route('dict-items.index', ['dict_type_id' => $dictItem->dict_type_id]) }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回字典项
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
    $dictItemFormProps = [
        'mode' => 'edit',
        'action' => route('dict-items.update', $dictItem),
        'method' => 'PUT',
        'csrf' => csrf_token(),
        'old' => [
            'label' => old('label', $dictItem->label),
            'value' => old('value', $dictItem->value),
            'sort' => old('sort', $dictItem->sort),
            'status' => old('status', (bool) $dictItem->status),
            'remark' => old('remark', $dictItem->remark),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('dict-items.index', ['dict_type_id' => $dictItem->dict_type_id]),
        'destroyUrl' => route('dict-items.destroy', $dictItem),
    ];
@endphp

    <div class="card max-w-2xl">
        <div
            data-vue-app
            data-component="dict-item-form"
            data-props='{!! vue_props($dictItemFormProps) !!}'
            x-ignore
        ></div>
    </div>
</x-app-layout>
