<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">角色管理</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">管理角色与权限分配</p>
            </div>
            <a href="{{ route('roles.create') }}" class="btn-primary">
                <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                新建角色
            </a>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">角色</th>
                        <th class="th">描述</th>
                        <th class="th">权限数</th>
                        <th class="th">用户数</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($roles as $role)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $role->id }}</td>
                            <td class="td">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $role->name }}</span>
                                    @if ($role->name === \App\Models\User::ROLE_ADMIN)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                                            <x-icon name="heroicon-o-star" class="h-3 w-3" />
                                            超级管理员
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $role->description ?? '—' }}</td>
                            <td class="td">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200">{{ $role->permissions_count }}</span>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $role->users_count }}</td>
                            <td class="td text-right whitespace-nowrap">
                                <a href="{{ route('roles.edit', $role) }}" class="btn-ghost">
                                    <x-icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                    编辑
                                </a>
                                @if ($role->name !== \App\Models\User::ROLE_ADMIN)
                                    <form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline" onsubmit="return confirm('确定要删除角色「{{ $role->name }}」吗？');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger-ghost">
                                            <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                            删除
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-shield-check" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">暂无角色</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $roles->links() }}
        </div>
    </div>
</x-app-layout>
