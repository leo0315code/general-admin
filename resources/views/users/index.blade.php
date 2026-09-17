<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">用户管理</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">管理系统用户与角色分配</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                {{-- 导入（选完文件自动上传） --}}
                @can('users.import')
                    <form method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data" class="inline-flex items-center gap-2">
                        @csrf
                        <label class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 rounded-lg cursor-pointer transition">
                            <x-icon name="heroicon-o-arrow-up-tray" class="h-4 w-4" />
                            导入
                            <input type="file" name="file" accept=".xlsx,.xls" class="hidden" required onchange="this.closest('form').submit()">
                        </label>
                    </form>
                    <a href="{{ route('users.import-template') }}" class="btn-secondary" title="下载导入模板">
                        <x-icon name="heroicon-o-document-arrow-down" class="h-4 w-4" />
                        模板
                    </a>
                @endcan
                @can('users.export')
                    <a href="{{ route('users.export', request()->query()) }}" class="btn-secondary" title="导出当前搜索结果">
                        <x-icon name="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                        导出
                    </a>
                @endcan
                <a href="{{ route('users.trash') }}" class="btn-secondary relative" title="已删除用户（回收站）">
                    <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                    回收站
                    @if ($trashedCount > 0)
                        <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 text-xs font-semibold">{{ $trashedCount }}</span>
                    @endif
                </a>
                @can('users.create')
                    <a href="{{ route('users.create') }}" class="btn-primary">
                        <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                        新建用户
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    {{-- 导入失败明细 --}}
    @if (session('import_errors'))
        <div class="mb-4 rounded-xl border border-red-200 dark:border-red-500/30 bg-red-50 dark:bg-red-500/10 p-4">
            <p class="text-sm font-medium text-red-700 dark:text-red-300 mb-2">以下行未导入成功：</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs text-red-600 dark:text-red-400 max-h-40 overflow-y-auto">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        {{-- 搜索栏 --}}
        <div class="card-header">
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="search" value="{{ $keyword }}" placeholder="搜索姓名或邮箱…" class="input pl-9">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">搜索</button>
                    @if ($keyword)
                        <a href="{{ route('users.index') }}" class="btn-secondary">清除</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 用户表格 --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">姓名</th>
                        <th class="th">邮箱</th>
                        <th class="th">角色</th>
                        <th class="th">注册时间</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $user->id }}</td>
                            <td class="td">
                                <div class="flex items-center gap-2.5">
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-xs font-semibold shrink-0">
                                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $user->email }}</td>
                            <td class="td">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($user->roles as $role)
                                        @if ($role->name === \App\Models\User::ROLE_ADMIN)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300">
                                                <x-icon name="heroicon-o-shield-check" class="h-3 w-3" />
                                                {{ $role->name }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                                                <x-icon name="heroicon-o-user" class="h-3 w-3" />
                                                {{ $role->name }}
                                            </span>
                                        @endif
                                    @empty
                                        <span class="text-xs text-gray-400 dark:text-gray-500">无角色</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $user->created_at->format('Y-m-d H:i') }}</td>
                            <td class="td text-right whitespace-nowrap">
                                <a href="{{ route('users.edit', $user) }}" class="btn-ghost" title="编辑">
                                    <x-icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                    编辑
                                </a>
                                @can('users.destroy')
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" onsubmit="return confirm('确定要删除用户「{{ $user->name }}」吗？');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger-ghost" title="删除">
                                                <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                                                删除
                                            </button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-users" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">没有找到用户</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 分页 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
