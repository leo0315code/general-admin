<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建字典类型</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">创建新的字典类型</p>
            </div>
        </div>
    </x-slot>

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-types.store') }}" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="label" for="name">类型名称</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name') }}" placeholder="如：订单状态" required autofocus>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="type">类型标识</label>
                <input id="type" name="type" type="text" class="input" value="{{ old('type') }}" placeholder="如：order_status（小写英文，用于代码）" required>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">小写英文标识，如 order_status / pay_method。</p>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="description">描述</label>
                <textarea id="description" name="description" rows="2" class="input" placeholder="用途说明（选填）">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700" @checked(old('status', true))>
                    <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                </label>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    创建类型
                </button>
                <a href="{{ route('dict-types.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
