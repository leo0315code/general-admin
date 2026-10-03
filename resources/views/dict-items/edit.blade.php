<x-app-layout>
    <x-slot name="header">
        @php
            // 来自「字典类型编辑页」时回跳该页，否则回字典项列表页
            // 仅放行后台前缀下的站内路径，避免开放重定向
            $adminPrefix = '/'.trim((string) config('app.admin_prefix', 'console'), '/').'/';
            $redirectTo = request('redirect_to');
            $redirectTo = is_string($redirectTo) && str_starts_with($redirectTo, $adminPrefix) ? $redirectTo : null;
            $backUrl = $redirectTo ?: route('dict-items.index', ['dict_type_id' => $dictItem->dict_type_id]);
        @endphp

        <x-page-header
            title="编辑字典项：{{ $dictItem->label }}"
            description="字典类型：{{ $dictItem->dictType->name }}"
            :back-url="$backUrl"
            :back-label="$redirectTo ? '返回类型' : '返回字典项'"
        >
        </x-page-header>
    </x-slot>

    <x-flash-messages />

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
        'indexUrl' => $backUrl,
        'redirectTo' => $redirectTo ?? '',
        'destroyUrl' => route('dict-items.destroy', $dictItem),
    ];
@endphp

    <div class="card max-w-2xl">
        <x-vue-mount component="dict-item-form" :props="$dictItemFormProps" />
    </div>
</x-app-layout>
