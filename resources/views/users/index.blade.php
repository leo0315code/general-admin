<x-app-layout>
    <x-slot name="header">
        <x-page-header title="用户管理" description="管理系统用户与角色分配">
            <x-slot name="actions">
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
            </x-slot>
        </x-page-header>
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

    @php
        // 用户管理页 Vue 组件 props（列表页样板：搜索/勾选/批量/表格由 Vue 渲染）
        $usersIndexProps = [
            'keyword' => $keyword ?? '',
            'users' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'roles' => $u->roles->pluck('name')->values(),
                'status' => (bool) $u->status,
                'must_change_password' => (bool) $u->must_change_password,
                'last_login_at' => $u->last_login_at?->format('Y-m-d H:i'),
                'last_login_ip' => $u->last_login_ip,
                'created_at' => $u->created_at->format('Y-m-d H:i'),
                'is_self' => $u->is(auth()->user()),
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'userBase' => rtrim(route('users.index'), '/'),
            'canManage' => auth()->user()->can('user.manage'),
            'canDestroy' => auth()->user()->can('users.destroy'),
            'routes' => [
                'bulk_delete' => route('users.bulk-delete'),
                'bulk_toggle' => route('users.bulk-toggle-status'),
            ],
        ];
    @endphp

    <div class="card">
        {{-- 列表交互层：搜索 / 勾选 / 批量 / 表格（Vue 组件 UsersIndex） --}}
        <x-vue-mount component="users-index" :props="$usersIndexProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$users" />
                <x-pagination :paginator="$users" />
            </div>
        </div>
    </div>
</x-app-layout>
