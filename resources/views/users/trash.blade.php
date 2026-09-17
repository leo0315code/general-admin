<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">用户回收站</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">已删除用户可在此还原或彻底清除</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('users.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回用户列表
                </a>
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        {{-- 搜索栏 --}}
        <div class="card-header">
            <form method="GET" action="{{ route('users.trash') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="search" value="{{ $keyword }}" placeholder="搜索已删除的姓名或邮箱…" class="input pl-9">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">搜索</button>
                    @if ($keyword)
                        <a href="{{ route('users.trash') }}" class="btn-secondary">清除</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 已删除用户表格 --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">姓名</th>
                        <th class="th">邮箱</th>
                        <th class="th">角色</th>
                        <th class="th">删除时间</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $user->id }}</td>
                            <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                            <td class="td">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $user->roles->pluck('name')->join(' / ') ?: '无角色' }}
                                </span>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $user->deleted_at->format('Y-m-d H:i') }}</td>
                            <td class="td text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('users.restore', $user->id) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn-ghost" title="还原该用户">
                                        <x-icon name="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                                        还原
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('users.force-destroy', $user->id) }}" class="inline"
                                      onsubmit="return confirm('彻底删除用户「{{ $user->name }}」将无法恢复，确定继续吗？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-danger-ghost" title="彻底删除（不可恢复）">
                                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                        彻底删除
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="td text-center py-12 text-gray-400 dark:text-gray-500">
                                回收站是空的
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 分页 --}}
        <div class="p-4 border-t border-gray-100 dark:border-gray-700/60">
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
