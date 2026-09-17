<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">编辑用户：{{ $user->name }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
            </div>
            <a href="{{ route('users.index') }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 基本信息 --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">基本信息</h3>
            </div>
            <form method="POST" action="{{ route('users.update', $user) }}" class="p-6 space-y-6">
                @csrf
                @method('PATCH')

                <div>
                    <label class="label" for="name">姓名</label>
                    <input id="name" name="name" type="text" class="input" value="{{ old('name', $user->name) }}" required>
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <label class="label" for="email">邮箱</label>
                    <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

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
                                    @checked(in_array($role->id, old('roles', $userRoleIds)))
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

                {{-- 可选：设置新密码 --}}
                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">设置新密码（选填）</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">如需在更新资料时同时修改密码，请填写以下两项。</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label" for="password">新密码</label>
                            <input id="password" name="password" type="password" class="input" autocomplete="new-password">
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <label class="label" for="password_confirmation">确认新密码</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    @can('users.update')
                        <button type="submit" class="btn-primary">
                            <x-icon name="heroicon-o-check" class="h-4 w-4" />
                            保存修改
                        </button>
                    @else
                        <p class="text-sm text-amber-600 dark:text-amber-400">当前角色没有「编辑用户」权限，仅可查看。</p>
                    @endcan
                </div>
            </form>
        </div>

        {{-- 右侧：账号信息 + 重置密码 + 危险操作 --}}
        <div class="space-y-6">
            {{-- 账号状态与登录痕迹 --}}
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <x-icon name="heroicon-o-user-circle" class="h-5 w-5 text-indigo-500" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">账号信息</h3>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">账号状态</span>
                        <span>
                            @if ($user->isActive())
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                    <x-icon name="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                    启用
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300">
                                    <x-icon name="heroicon-o-x-circle" class="h-3.5 w-3.5" />
                                    停用
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">最后登录</span>
                        <span class="text-gray-700 dark:text-gray-200">
                            {{ $user->last_login_at?->format('Y-m-d H:i') ?? '从未登录' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">最后登录 IP</span>
                        <span class="font-mono text-xs text-gray-700 dark:text-gray-200">{{ $user->last_login_ip ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">强制改密</span>
                        <span>
                            @if ($user->must_change_password)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                                    <x-icon name="heroicon-o-key" class="h-3.5 w-3.5" />
                                    下次登录需改密
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">否</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            @can('users.reset-password')
                <div class="card">
                    <div class="card-header flex items-center gap-2">
                        <x-icon name="heroicon-o-key" class="h-5 w-5 text-amber-500" />
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">重置密码</h3>
                    </div>
                    <form method="POST" action="{{ route('users.reset-password', $user) }}" class="p-5 space-y-4" onsubmit="return confirm('确定要为该用户重置密码吗？');">
                        @csrf
                        <div>
                            <label class="label" for="new_password">新密码</label>
                            <input id="new_password" name="new_password" type="password" class="input" required minlength="8">
                            <x-input-error :messages="$errors->get('new_password')" class="mt-2" />
                        </div>
                        <div>
                            <label class="label" for="new_password_confirmation">确认新密码</label>
                            <input id="new_password_confirmation" name="new_password_confirmation" type="password" class="input" required>
                        </div>
                        <button type="submit" class="btn-primary w-full justify-center">
                            <x-icon name="heroicon-o-key" class="h-4 w-4" />
                            重置密码
                        </button>
                    </form>
                </div>
            @endcan

            @can('users.destroy')
                @unless ($user->is(auth()->user()))
                    <div class="card border-red-200 dark:border-red-500/30">
                        <div class="card-header flex items-center gap-2 border-red-100 dark:border-red-500/20">
                            <x-icon name="heroicon-o-exclamation-triangle" class="h-5 w-5 text-red-500" />
                            <h3 class="text-base font-semibold text-red-600 dark:text-red-400">危险操作</h3>
                        </div>
                        <div class="p-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">删除后用户将无法登录（软删除，可在数据库中恢复）。</p>
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('确定要删除用户「{{ $user->name }}」吗？');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger-ghost w-full justify-center border border-red-200 dark:border-red-500/30 rounded-lg py-2">
                                    <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                    删除用户
                                </button>
                            </form>
                        </div>
                    </div>
                @endunless
            @endcan
        </div>
    </div>
</x-app-layout>
