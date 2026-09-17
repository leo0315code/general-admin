<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建用户" description="创建系统用户并分配角色" />
    </x-slot>

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('users.store') }}" class="p-6 space-y-6">
            @csrf

            {{-- 姓名 --}}
            <x-form-field name="name" label="姓名" :required="true">
                <input id="name" name="name" type="text" class="input @error('name') input-error @enderror" value="{{ old('name') }}" placeholder="请输入姓名" required autofocus>
            </x-form-field>

            {{-- 邮箱 --}}
            <x-form-field name="email" label="邮箱" :required="true">
                <input id="email" name="email" type="email" class="input @error('email') input-error @enderror" value="{{ old('email') }}" placeholder="name@example.com" required>
            </x-form-field>

            {{-- 密码 --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-form-field name="password" label="密码" :required="true">
                    <input id="password" name="password" type="password" class="input @error('password') input-error @enderror" required autocomplete="new-password">
                </x-form-field>
                <x-form-field name="password_confirmation" label="确认密码" :required="true">
                    <input id="password_confirmation" name="password_confirmation" type="password" class="input @error('password_confirmation') input-error @enderror" required autocomplete="new-password">
                </x-form-field>
            </div>

            {{-- 角色分配（多选） --}}
            <x-form-field name="roles" label="角色分配" hint="可多选；用户的权限为所分配角色权限的并集。">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->id }}"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                                @checked(in_array($role->id, old('roles', [])))
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $role->name }}</span>
                            @if ($role->name === \App\Models\User::ROLE_ADMIN)
                                <x-icon name="heroicon-o-shield-check" class="h-4 w-4 text-violet-500 dark:text-violet-400" />
                            @endif
                        </label>
                    @endforeach
                </div>
            </x-form-field>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="创建用户" icon="heroicon-o-plus" />
                <a href="{{ route('users.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
