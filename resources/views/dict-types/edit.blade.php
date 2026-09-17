<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">编辑字典类型：{{ $dictType->name }}</h2>
            </div>
            <a href="{{ route('dict-types.index') }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        </div>
    </x-slot>

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-types.update', $dictType) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="label" for="name">类型名称</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name', $dictType->name) }}" required autofocus>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="type">类型标识</label>
                <input id="type" name="type" type="text" class="input" value="{{ old('type', $dictType->type) }}" required>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="description">描述</label>
                <textarea id="description" name="description" rows="2" class="input" placeholder="用途说明（选填）">{{ old('description', $dictType->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700" @checked(old('status', $dictType->status))>
                    <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                </label>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    保存修改
                </button>
                <form method="POST" action="{{ route('dict-types.destroy', $dictType) }}" onsubmit="return confirm('确定要删除类型「{{ $dictType->name }}」及其全部字典项吗？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2">
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        删除类型
                    </button>
                </form>
            </div>
        </form>
    </div>
</x-app-layout>
