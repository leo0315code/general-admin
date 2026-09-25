<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑用户：{{ $user->name }}" description="{{ $user->email }}" :back-url="route('users.index')">
            <x-slot name="actions">
                <a href="{{ route('users.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 用户编辑表单 Vue 组件 props（基本信息 Vue 化，右侧卡片保留 Blade）
        $userFormProps = [
            'mode' => 'edit',
            'action' => route('users.update', $user),
            'method' => 'PATCH',
            'csrf' => csrf_token(),
            'old' => [
                'name' => old('name', $user->name),
                'email' => old('email', $user->email),
                'roles' => old('roles', $userRoleIds),
            ],
            'errors' => $errors->toArray(),
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_admin' => $role->name === \App\Models\User::ROLE_ADMIN,
            ])->values(),
            'userRoleIds' => $userRoleIds,
            'canUpdate' => auth()->user()->can('users.update'),
            'indexUrl' => route('users.index'),
        ];
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 基本信息（Vue 组件 UserForm） --}}
        <div class="lg:col-span-2 card">
            <div class="card-header">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">基本信息</h3>
            </div>
            <x-vue-mount component="users-form" :props="$userFormProps" />
        </div>

        {{-- 右侧：账号信息 + 重置密码 + 危险操作 --}}
        <div class="space-y-6">
            {{-- 账号状态与登录痕迹 --}}
            <div class="card">
                <div class="card-header flex items-center gap-2">
                    <x-icon name="heroicon-o-user-circle" class="h-5 w-5 text-primary-500" />
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">账号信息</h3>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">账号状态</span>
                        <span>
                            @if ($user->isActive())
                                <x-status-badge type="success" icon="heroicon-o-check-circle">启用</x-status-badge>
                            @else
                                <x-status-badge type="danger" icon="heroicon-o-x-circle">停用</x-status-badge>
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
                                <x-status-badge type="warning" icon="heroicon-o-key">下次登录需改密</x-status-badge>
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
                        <x-icon name="heroicon-o-key" class="h-5 w-5 text-warning-500" />
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">重置密码</h3>
                    </div>
                    <form method="POST" action="{{ route('users.reset-password', $user) }}" class="p-5 space-y-4"
                          data-confirm-title="确定要为该用户重置密码吗？"
                          data-confirm-message="重置后该用户下次登录需先修改密码。">
                        @csrf
                        <x-form-field name="new_password" label="新密码" :required="true">
                            <input id="new_password" name="new_password" type="password" class="input @error('new_password') input-error @enderror" required minlength="8">
                        </x-form-field>
                        <x-form-field name="new_password_confirmation" label="确认新密码" :required="true">
                            <input id="new_password_confirmation" name="new_password_confirmation" type="password" class="input @error('new_password_confirmation') input-error @enderror" required>
                        </x-form-field>
                        <button type="submit" class="btn-primary w-full justify-center"
                                @click.prevent="window.__ui.confirmModal.open($el.closest('form'))">
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
                            <x-icon name="heroicon-o-exclamation-triangle" class="h-5 w-5 text-danger-500" />
                            <h3 class="text-base font-semibold text-danger-600 dark:text-danger-400">危险操作</h3>
                        </div>
                        <div class="p-5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">删除后用户将无法登录（软删除，可在数据库中恢复）。</p>
                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                  data-confirm-title="确定要删除用户「{{ $user->name }}」吗？"
                                  data-confirm-message="删除后将无法登录（软删除，可在数据库中恢复）。">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-danger-ghost w-full justify-center border border-red-200 dark:border-red-500/30 rounded-lg py-2"
                                        @click.prevent="window.__ui.confirmModal.open($el.closest('form'))">
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
