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

        <x-data-table
            :columns="[
                ['key' => null, 'label' => '名称'],
                ['key' => null, 'label' => '权限标识'],
                ['key' => null, 'label' => '路由'],
                ['key' => null, 'label' => '排序'],
                ['key' => null, 'label' => '状态'],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
        >
            <x-slot name="rows">
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
                                    'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => $menu->isDir(),
                                    'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300' => $menu->isMenu(),
                                    'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300' => $menu->isButton(),
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
                                <x-status-badge type="success" icon="heroicon-o-check-circle">启用</x-status-badge>
                            @else
                                <x-status-badge type="neutral" icon="heroicon-o-no-symbol">停用</x-status-badge>
                            @endif
                        </td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                @can('menus.create')
                                    @if (! $menu->isButton())
                                        <x-icon-button icon="heroicon-o-plus" :href="route('menus.create', ['pid' => $menu->id])" title="在该节点下新增子节点" />
                                    @endif
                                @endcan

                                @can('menus.update')
                                    <x-icon-button icon="heroicon-o-pencil-square" :href="route('menus.edit', $menu)" title="编辑" variant="primary" />
                                    <form method="POST" action="{{ route('menus.toggle-status', $menu) }}" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <x-icon-button :icon="$menu->status ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle'"
                                                       :title="$menu->status ? '停用' : '启用'" />
                                    </form>
                                @endcan

                                @can('menus.destroy')
                                    <form method="POST" action="{{ route('menus.destroy', $menu) }}" class="inline"
                                          data-confirm-title="确定要删除「{{ $menu->title }}」吗？"
                                          data-confirm-message="对应权限记录将一并清理。">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button icon="heroicon-o-trash" title="删除" variant="danger"
                                                       @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-rectangle-stack" title="还没有任何菜单节点" :colspan="6" />
                @endforelse
            </x-slot>
        </x-data-table>
    </div>

    <x-confirm-modal />
</x-app-layout>
