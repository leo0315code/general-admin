<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建用户" description="创建系统用户并分配角色" />
    </x-slot>

    @php
        // 用户创建表单 Vue 组件 props（表单样板：原生 POST + 服务端校验回显）
        $userFormProps = [
            'mode' => 'create',
            'action' => route('users.store'),
            'method' => 'POST',
            'csrf' => csrf_token(),
            'old' => [
                'name' => old('name', ''),
                'email' => old('email', ''),
                'roles' => old('roles', []),
            ],
            'errors' => $errors->toArray(),
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'is_admin' => $role->name === \App\Models\User::ROLE_ADMIN,
            ])->values(),
            'indexUrl' => route('users.index'),
        ];
    @endphp

    <div class="card max-w-2xl">
        {{-- 用户创建表单（Vue 组件 UserForm） --}}
        <div
            data-vue-app
            data-component="users-form"
            data-props='{!! vue_props($userFormProps) !!}'
            x-ignore
        ></div>
    </div>
</x-app-layout>
