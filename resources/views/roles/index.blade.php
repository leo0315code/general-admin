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

    <div class="card">
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => '角色', 'sortable' => true],
                ['key' => null, 'label' => '描述'],
                ['key' => null, 'label' => '权限数'],
                ['key' => null, 'label' => '用户数'],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($roles as $role)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ $role->id }}</td>
                        <td class="td">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $role->name }}</span>
                                @if ($role->name === \App\Models\User::ROLE_ADMIN)
                                    <x-status-badge type="info" icon="heroicon-o-star" size="xs">超级管理员</x-status-badge>
                                @endif
                            </div>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $role->description ?? '—' }}</td>
                        <td class="td">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200">{{ $role->permissions_count }}</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $role->users_count }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <x-icon-button icon="heroicon-o-pencil-square" :href="route('roles.edit', $role)" title="编辑" variant="primary" />
                                @if ($role->name !== \App\Models\User::ROLE_ADMIN)
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline"
                                          data-confirm-title="确定要删除角色「{{ $role->name }}」吗？"
                                          data-confirm-message="删除后该角色及其权限分配将一并移除。">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-button icon="heroicon-o-trash" title="删除" variant="danger"
                                                       @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-shield-check" title="暂无角色" :colspan="6" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$roles" />
                <x-pagination :paginator="$roles" />
            </div>
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
