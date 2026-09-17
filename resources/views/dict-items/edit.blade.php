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

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-items.update', $dictItem) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <x-form-field name="label" label="字典项名称" :required="true">
                <input id="label" name="label" type="text" class="input @error('label') input-error @enderror" value="{{ old('label', $dictItem->label) }}" required autofocus>
            </x-form-field>

            <x-form-field name="value" label="字典项值" :required="true">
                <input id="value" name="value" type="text" class="input @error('value') input-error @enderror" value="{{ old('value', $dictItem->value) }}" required>
            </x-form-field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-form-field name="sort" label="排序">
                    <input id="sort" name="sort" type="number" class="input @error('sort') input-error @enderror" value="{{ old('sort', $dictItem->sort) }}" min="0" max="9999">
                </x-form-field>

                <x-form-field name="status" label="状态">
                    <label class="inline-flex items-center gap-2 cursor-pointer mt-2">
                        <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-700" @checked(old('status', $dictItem->status))>
                        <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                    </label>
                </x-form-field>
            </div>

            <x-form-field name="remark" label="备注">
                <input id="remark" name="remark" type="text" class="input @error('remark') input-error @enderror" value="{{ old('remark', $dictItem->remark) }}" placeholder="选填">
            </x-form-field>

            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="保存修改" icon="heroicon-o-check" />
                <form method="POST" action="{{ route('dict-items.destroy', $dictItem) }}"
                      data-confirm-title="确定要删除字典项「{{ $dictItem->label }}」吗？"
                      data-confirm-message="删除后该字典项将无法恢复。">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                            @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))">
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        删除字典项
                    </button>
                </form>
            </div>
        </form>
    </div>

    <x-confirm-modal />
</x-app-layout>
