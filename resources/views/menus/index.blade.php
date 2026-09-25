<x-app-layout>
    <x-slot name="header">
        <x-page-header title="菜单管理" description="维护菜单与权限节点（目录 / 菜单 / 按钮），保存即同步权限——菜单即权限">
            <x-slot name="actions">
                @can('menus.create')
                    <a href="{{ route('menus.create') }}" class="btn-primary">
                        <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                        新建节点
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 菜单管理页 Vue 组件 props（树形扁平渲染，深度缩进）
        $menusIndexProps = [
            'rows' => collect($rows)->map(fn ($row) => [
                'id' => $row['menu']->id,
                'title' => $row['menu']->title,
                'icon' => $row['menu']->icon,
                'type' => $row['menu']->type,
                'type_label' => $row['menu']->typeLabel(),
                'permission_name' => $row['menu']->permission_name,
                'route' => $row['menu']->route,
                'sort' => $row['menu']->sort,
                'status' => (bool) $row['menu']->status,
                'depth' => $row['depth'],
            ])->values(),
            'menusBase' => rtrim(route('menus.index'), '/'),
            'can' => [
                'create' => auth()->user()->can('menus.create'),
                'update' => auth()->user()->can('menus.update'),
                'destroy' => auth()->user()->can('menus.destroy'),
            ],
        ];
    @endphp

    <div class="card">
        <div class="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>共 {{ count($rows) }} 个节点</span>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <span>停用的菜单不会出现在侧边栏，权限仍保留</span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><span class="h-2 w-2 rounded-full bg-primary-500"></span>目录</span>
                <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><span class="h-2 w-2 rounded-full bg-success-500"></span>菜单</span>
                <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><span class="h-2 w-2 rounded-full bg-warning-500"></span>按钮</span>
            </div>
        </div>

        {{-- 菜单树表格（Vue 组件 MenusIndex） --}}
        <x-vue-mount component="menus-index" :props="$menusIndexProps" />
    </div>
</x-app-layout>
