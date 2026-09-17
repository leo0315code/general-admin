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

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-items.store', ['dict_type_id' => $dictType->id]) }}" class="p-6 space-y-6">
            @csrf

            <x-form-field name="label" label="字典项名称" :required="true">
                <input id="label" name="label" type="text" class="input @error('label') input-error @enderror" value="{{ old('label') }}" placeholder="如：待付款" required autofocus>
            </x-form-field>

            <x-form-field name="value" label="字典项值" :required="true">
                <input id="value" name="value" type="text" class="input @error('value') input-error @enderror" value="{{ old('value') }}" placeholder="如：pending（同一类型下唯一）" required>
            </x-form-field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-form-field name="sort" label="排序">
                    <input id="sort" name="sort" type="number" class="input @error('sort') input-error @enderror" value="{{ old('sort', 0) }}" min="0" max="9999">
                </x-form-field>

                <x-form-field name="status" label="状态">
                    <label class="inline-flex items-center gap-2 cursor-pointer mt-2">
                        <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-700" @checked(old('status', true))>
                        <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                    </label>
                </x-form-field>
            </div>

            <x-form-field name="remark" label="备注">
                <input id="remark" name="remark" type="text" class="input @error('remark') input-error @enderror" value="{{ old('remark') }}" placeholder="选填">
            </x-form-field>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="创建字典项" icon="heroicon-o-plus" />
                <a href="{{ route('dict-items.index', ['dict_type_id' => $dictType->id]) }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
