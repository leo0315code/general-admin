<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建角色</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">创建角色并分配权限</p>
            </div>
        </div>
    </x-slot>

    <div class="card max-w-2xl">
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

            {{-- 权限分配（多选） --}}
            <div>
                <label class="label">权限分配</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">勾选该角色可执行的权限；admin 角色自动拥有全部权限。</p>
                <div class="space-y-2">
                    @foreach ($permissions as $permission)
                        <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission->id }}"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(in_array($permission->id, old('permissions', [])))
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $permission->label ?? $permission->name }}</span>
                            <span class="text-xs font-mono text-gray-400 dark:text-gray-500 ml-auto">{{ $permission->name }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
            </div>

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
