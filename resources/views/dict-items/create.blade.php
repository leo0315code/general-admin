<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建字典项</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">字典类型：{{ $dictType->name }}</p>
            </div>
            <a href="{{ route('dict-items.index', ['dict_type_id' => $dictType->id]) }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回字典项
            </a>
        </div>
    </x-slot>

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-items.store', ['dict_type_id' => $dictType->id]) }}" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="label" for="label">字典项名称</label>
                <input id="label" name="label" type="text" class="input" value="{{ old('label') }}" placeholder="如：待付款" required autofocus>
                <x-input-error :messages="$errors->get('label')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="value">字典项值</label>
                <input id="value" name="value" type="text" class="input" value="{{ old('value') }}" placeholder="如：pending（同一类型下唯一）" required>
                <x-input-error :messages="$errors->get('value')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="sort">排序</label>
                    <input id="sort" name="sort" type="number" class="input" value="{{ old('sort', 0) }}" min="0" max="9999">
                    <x-input-error :messages="$errors->get('sort')" class="mt-2" />
                </div>
                <div>
                    <label class="label">状态</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer mt-2">
                        <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700" @checked(old('status', true))>
                        <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="label" for="remark">备注</label>
                <input id="remark" name="remark" type="text" class="input" value="{{ old('remark') }}" placeholder="选填">
                <x-input-error :messages="$errors->get('remark')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    创建字典项
                </button>
                <a href="{{ route('dict-items.index', ['dict_type_id' => $dictType->id]) }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
