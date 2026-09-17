<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">菜单管理</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">维护菜单与权限节点（目录 / 菜单 / 按钮），保存即同步权限——菜单即权限</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('menus.create')
                    <a href="{{ route('menus.create') }}" class="btn-primary">
                        <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                        新建节点
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <div class="card-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <span>共 {{ count($rows) }} 个节点</span>
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <span>停用的菜单不会出现在侧边栏，权限仍保留</span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>目录</span>
                <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>菜单</span>
                <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400"><span class="h-2 w-2 rounded-full bg-amber-500"></span>按钮</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-800/60">
                    <tr>
                        <th class="th">名称</th>
                        <th class="th">权限标识</th>
                        <th class="th">路由</th>
                        <th class="th">排序</th>
                        <th class="th">状态</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($rows as $row)
                        @php($menu = $row['menu'])
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td">
                                <div class="flex items-center gap-2" style="padding-left: {{ $row['depth'] * 22 }}px">
                                    @if ($row['depth'] > 0)
                                        <span class="text-gray-300 dark:text-gray-600 select-none">└</span>
                                    @endif

                                    @if ($menu->icon)
                                        <x-icon :name="$menu->icon" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                    @endif

                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $menu->title }}</span>

                                    <span @class([
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium',
                                        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' => $menu->isDir(),
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => $menu->isMenu(),
                                        'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' => $menu->isButton(),
                                    ])>{{ $menu->typeLabel() }}</span>
                                </div>
                            </td>
                            <td class="td">
                                @if ($menu->permission_name)
                                    <code class="text-xs font-mono px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $menu->permission_name }}</code>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">
                                {{ $menu->route ?? '—' }}
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $menu->sort }}</td>
                            <td class="td">
                                @if ($menu->status)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                        <x-icon name="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                        启用
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        <x-icon name="heroicon-o-no-symbol" class="h-3.5 w-3.5" />
                                        停用
                                    </span>
                                @endif
                            </td>
                            <td class="td text-right whitespace-nowrap">
                                @can('menus.create')
                                    @if (! $menu->isButton())
                                        <a href="{{ route('menus.create', ['pid' => $menu->id]) }}" class="btn-ghost" title="在该节点下新增子节点">
                                            <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                                            子节点
                                        </a>
                                    @endif
                                @endcan

                                @can('menus.update')
                                    <a href="{{ route('menus.edit', $menu) }}" class="btn-ghost">
                                        <x-icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                        编辑
                                    </a>
                                    <form method="POST" action="{{ route('menus.toggle-status', $menu) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-ghost">
                                            <x-icon :name="$menu->status ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle'" class="h-4 w-4" />
                                            {{ $menu->status ? '停用' : '启用' }}
                                        </button>
                                    </form>
                                @endcan

                                @can('menus.destroy')
                                    <form method="POST" action="{{ route('menus.destroy', $menu) }}" class="inline" onsubmit="return confirm('确定要删除「{{ $menu->title }}」吗？对应权限记录将一并清理。');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger-ghost">
                                            <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                            删除
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-rectangle-stack" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">还没有任何菜单节点</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
