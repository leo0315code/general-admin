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

    <div class="card" x-data="listSelection()">
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

        {{-- 批量操作条 --}}
        <div class="px-5 pt-4">
            @can('user.manage')
                <x-bulk-actions
                    :action-url="route('users.bulk-delete')"
                    method="POST"
                    confirm-title="确定删除选中的用户吗？"
                    confirm-message="删除后将进入回收站（软删除），可在回收站中还原。"
                >
                    <button type="submit" class="btn-danger-ghost" @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))">
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        批量删除
                    </button>
                    <button type="submit" class="btn-ghost" @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))">
                        <x-icon name="heroicon-o-arrow-path" class="h-4 w-4" />
                        批量启停
                    </button>
                </x-bulk-actions>
            @endcan
        </div>

        {{-- 用户表格 --}}
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => '姓名', 'sortable' => true],
                ['key' => 'email', 'label' => '邮箱', 'sortable' => true],
                ['key' => null, 'label' => '角色'],
                ['key' => 'status', 'label' => '状态', 'sortable' => true],
                ['key' => 'last_login_at', 'label' => '最后登录', 'sortable' => true],
                ['key' => 'created_at', 'label' => '注册时间', 'sortable' => true],
                ['key' => null, 'label' => '操作', 'align' => 'right'],
            ]"
            :selectable="true"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($users as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td w-10">
                            <input type="checkbox" value="{{ $user->id }}" data-select-row x-model="selectedIds" @change="syncSelectAll" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500" aria-label="选择用户 {{ $user->name }}">
                        </td>
                        <td class="td text-gray-500 dark:text-gray-400">{{ $user->id }}</td>
                        <td class="td">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-semibold shrink-0">
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
                                        <x-status-badge type="info" icon="heroicon-o-shield-check" size="xs">{{ $role->name }}</x-status-badge>
                                    @else
                                        <x-status-badge type="info" icon="heroicon-o-user" size="xs">{{ $role->name }}</x-status-badge>
                                    @endif
                                @empty
                                    <span class="text-xs text-gray-400 dark:text-gray-500">无角色</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="td">
                            <div class="flex flex-wrap items-center gap-1.5">
                                @if ($user->isActive())
                                    <x-status-badge type="success" icon="heroicon-o-check-circle">启用</x-status-badge>
                                @else
                                    <x-status-badge type="danger" icon="heroicon-o-x-circle">停用</x-status-badge>
                                @endif
                                @if ($user->must_change_password)
                                    <x-status-badge type="warning" icon="heroicon-o-key" size="xs" title="首次登录需修改密码">待改密</x-status-badge>
                                @endif
                            </div>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">
                            @if ($user->last_login_at)
                                {{ $user->last_login_at->format('Y-m-d H:i') }}
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $user->last_login_ip ?? '—' }}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">从未登录</span>
                            @endif
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $user->created_at->format('Y-m-d H:i') }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <x-icon-button icon="heroicon-o-pencil-square" :href="route('users.edit', $user)" title="编辑" variant="primary" />

                                @unless ($user->is(auth()->user()))
                                    <form method="POST" action="{{ route('users.toggle-status', $user) }}" class="inline"
                                          data-confirm-title="确定要{{ $user->isActive() ? '停用' : '启用' }}用户「{{ $user->name }}」吗？"
                                          data-confirm-message="{{ $user->isActive() ? '停用后该用户将无法登录。' : '启用后该用户可正常登录。' }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-icon-button :icon="$user->isActive() ? 'heroicon-o-pause' : 'heroicon-o-play'"
                                                       :title="$user->isActive() ? '停用（无法登录）' : '启用'"
                                                       @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                    </form>
                                @endunless

                                @can('users.destroy')
                                    @unless ($user->is(auth()->user()))
                                        <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline"
                                              data-confirm-title="确定要删除用户「{{ $user->name }}」吗？"
                                              data-confirm-message="删除后将无法登录（软删除，可在回收站中还原）。">
                                            @csrf
                                            @method('DELETE')
                                            <x-icon-button icon="heroicon-o-trash" title="删除" variant="danger"
                                                           @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))" />
                                        </form>
                                    @endunless
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-users" title="没有找到用户" :colspan="9" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$users" />
                <x-pagination :paginator="$users" />
            </div>
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
