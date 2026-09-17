<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建字典类型" description="创建新的字典类型" />
    </x-slot>

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('dict-types.store') }}" class="p-6 space-y-6">
            @csrf

            <x-form-field name="name" label="类型名称" :required="true">
                <input id="name" name="name" type="text" class="input @error('name') input-error @enderror" value="{{ old('name') }}" placeholder="如：订单状态" required autofocus>
            </x-form-field>

            <x-form-field name="type" label="类型标识" :required="true" hint="小写英文标识，如 order_status / pay_method。">
                <input id="type" name="type" type="text" class="input @error('type') input-error @enderror" value="{{ old('type') }}" placeholder="如：order_status（小写英文，用于代码）" required>
            </x-form-field>

            <x-form-field name="description" label="描述">
                <textarea id="description" name="description" rows="2" class="input @error('description') input-error @enderror" placeholder="用途说明（选填）">{{ old('description') }}</textarea>
            </x-form-field>

            <x-form-field name="status" label="状态">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="status" value="1" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-700" @checked(old('status', true))>
                    <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
                </label>
            </x-form-field>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="创建类型" icon="heroicon-o-plus" />
                <a href="{{ route('dict-types.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
