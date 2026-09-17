<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建角色</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">创建角色并分配权限</p>
            </div>
        </div>
    </x-slot>

    <div class="card">
        <form method="POST" action="{{ route('roles.store') }}" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="label" for="name">角色标识</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name') }}" placeholder="如：operator（小写英文，用于代码判断）" required autofocus>
                <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">小写英文标识，用于代码中的角色判断（如 admin / editor）。</p>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="description">描述</label>
                <textarea id="description" name="description" rows="2" class="input" placeholder="角色职责说明（选填）">{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            {{-- 权限分配：按菜单树勾选（目录 → 菜单 → 按钮） --}}
            @include('roles.partials.permission-picker')

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    创建角色
                </button>
                <a href="{{ route('roles.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
