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

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-types.update', $dictType) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <x-form-field name="name" label="类型名称" :required="true">
                <input id="name" name="name" type="text" class="input @error('name') input-error @enderror" value="{{ old('name', $dictType->name) }}" required autofocus>
            </x-form-field>

            <x-form-field name="type" label="类型标识" :required="true">
                <input id="type" name="type" type="text" class="input @error('type') input-error @enderror" value="{{ old('type', $dictType->type) }}" required>
            </x-form-field>

            <x-form-field name="description" label="描述">
                <textarea id="description" name="description" rows="2" class="input @error('description') input-error @enderror" placeholder="用途说明（选填）">{{ old('description', $dictType->description) }}</textarea>
            </x-form-field>

            <x-form-field name="status" label="状态">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-700" @checked(old('status', $dictType->status))>
                    <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                </label>
            </x-form-field>

            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="保存修改" icon="heroicon-o-check" />
                <form method="POST" action="{{ route('dict-types.destroy', $dictType) }}"
                      data-confirm-title="确定要删除类型「{{ $dictType->name }}」及其全部字典项吗？"
                      data-confirm-message="该类型下的所有字典项将一并删除，此操作不可恢复。">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                            @click.prevent="window.__ui.confirmModal.open($el.closest('form'))">
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        删除类型
                    </button>
                </form>
            </div>
        </form>
    </div>
</x-app-layout>
