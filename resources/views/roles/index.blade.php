<x-app-layout>
    <x-slot name="header">
        <x-page-header title="角色管理" description="管理角色与权限分配">
            <x-slot name="actions">
                <a href="{{ route('roles.create') }}" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    新建角色
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 角色管理页 Vue 组件 props（列表页样板推广：表格由 Vue 渲染，分页保留 Blade）
        $rolesIndexProps = [
            'roles' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_admin' => $role->name === \App\Models\User::ROLE_ADMIN,
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'roleBase' => rtrim(route('roles.index'), '/'),
        ];
    @endphp

    <div class="card">
        {{-- 列表交互层：表格（Vue 组件 RolesIndex） --}}
        <x-vue-mount component="roles-index" :props="$rolesIndexProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$roles" />
                <x-pagination :paginator="$roles" />
            </div>
        </div>
    </div>
</x-app-layout>
