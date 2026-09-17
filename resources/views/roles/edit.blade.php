<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">编辑角色：{{ $role->name }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">修改角色信息与权限分配</p>
            </div>
            <a href="{{ route('roles.index') }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('roles.update', $role) }}" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            <div>
                <label class="label" for="name">角色标识</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name', $role->name) }}" @readonly($role->name === \App\Models\User::ROLE_ADMIN) required>
                @if ($role->name === \App\Models\User::ROLE_ADMIN)
                    <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">内置 admin 角色的标识不允许修改。</p>
                @endif
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="description">描述</label>
                <textarea id="description" name="description" rows="2" class="input" placeholder="角色职责说明（选填）">{{ old('description', $role->description) }}</textarea>
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
                                @checked(in_array($permission->id, old('permissions', $rolePermissionIds)))
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
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    保存修改
                </button>
                @if ($role->name !== \App\Models\User::ROLE_ADMIN)
                    <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('确定要删除角色「{{ $role->name }}」吗？');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2">
                            <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                            删除角色
                        </button>
                    </form>
                @endif
            </div>
        </form>
    </div>
</x-app-layout>
