<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建用户</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">创建系统用户并分配角色</p>
            </div>
        </div>
    </x-slot>

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('users.store') }}" class="p-6 space-y-6">
            @csrf

            {{-- 姓名 --}}
            <div>
                <label class="label" for="name">姓名</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name') }}" placeholder="请输入姓名" required autofocus>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            {{-- 邮箱 --}}
            <div>
                <label class="label" for="email">邮箱</label>
                <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" placeholder="name@example.com" required>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            {{-- 密码 --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="password">密码</label>
                    <input id="password" name="password" type="password" class="input" required autocomplete="new-password">
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <label class="label" for="password_confirmation">确认密码</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input" required autocomplete="new-password">
                </div>
            </div>

            {{-- 角色分配（多选） --}}
            <div>
                <label class="label">角色分配</label>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">可多选；用户的权限为所分配角色权限的并集。</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->id }}"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @checked(in_array($role->id, old('roles', [])))
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $role->name }}</span>
                            @if ($role->name === \App\Models\User::ROLE_ADMIN)
                                <x-icon name="heroicon-o-shield-check" class="h-4 w-4 text-violet-500 dark:text-violet-400" />
                            @endif
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('roles')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    创建用户
                </button>
                <a href="{{ route('users.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
