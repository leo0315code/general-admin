<x-app-layout>
    <x-slot name="header">
        <x-page-header title="用户回收站" description="已删除用户可在此还原或彻底清除" :back-url="route('users.index')" back-label="返回用户列表" :breadcrumbs="[['label' => '用户管理', 'url' => route('users.index')], ['label' => '回收站']]">
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 用户回收站 Vue 组件 props（表格由 Vue 渲染，分页保留 Blade）
        $usersTrashProps = [
            'keyword' => $keyword ?? '',
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roles->pluck('name')->join(' / ') ?: '无角色',
                'deleted_at' => $u->deleted_at->format('Y-m-d H:i'),
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'userBase' => rtrim(route('users.index'), '/'),
        ];
    @endphp

    <div class="card">
        {{-- 搜索 + 表格（Vue 组件 UsersTrash） --}}
        <x-vue-mount component="users-trash" :props="$usersTrashProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$users" />
                <x-pagination :paginator="$users" />
            </div>
        </div>
    </div>
</x-app-layout>
