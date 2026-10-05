<x-app-layout>
    <x-slot name="header">
        <x-page-header title="操作日志" description="登录审计与后台操作审计">
            <x-slot name="actions">
                @can('log.export')
                    <a
                        href="{{ route('logs.export', array_filter(request()->only(['search', 'action', 'date', 'scope']))) }}"
                        class="btn-secondary"
                    >
                        <x-icon name="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                        <span>导出</span>
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        // 操作日志页 Vue 组件 props（筛选栏 + 只读表格由 Vue 渲染，分页保留 Blade）
        $logsIndexProps = [
            'keyword' => $keyword ?? '',
            'action' => $action ?? '',
            'date' => $date ?? '',
            'scope' => $scope ?? 'all',
            'actionOptions' => $actionOptions ?? [],
            'logs' => $logs->map(fn ($log) => [
                'id' => $log->id,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'username' => $log->username,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'description' => $log->description,
                'ip' => $log->ip,
            ])->values(),
            'sort' => $sort ?? 'id',
            'sortDir' => $dir ?? 'desc',
            'currentUrl' => url()->current(),
            'query' => request()->query(),
            'detailUrl' => route('logs.show', ['log' => '__ID__']),
        ];
    @endphp

    {{-- 范围 Tab：全部日志 / 只看登录记录（不增菜单/权限，挂在同一页） --}}
    <div class="mb-4 flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-1 w-fit">
        @php
            $tabBase = ['search' => $keyword, 'action' => $action, 'date' => $date];
        @endphp
        <a
            href="{{ route('logs.index', array_filter($tabBase)) }}"
            class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'all' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
        >全部日志</a>
        <a
            href="{{ route('logs.index', array_filter(array_merge($tabBase, ['scope' => 'login']))) }}"
            class="px-4 py-1.5 text-sm font-medium rounded-md transition {{ $scope === 'login' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}"
        >登录记录</a>
    </div>

    <div class="card">
        {{-- 筛选栏 + 日志表格（Vue 组件 LogsIndex） --}}
        <x-vue-mount component="logs-index" :props="$logsIndexProps" />

        {{-- 分页 + 每页条数（Blade 渲染，GET 整页刷新） --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$logs" />
                <x-pagination :paginator="$logs" />
            </div>
        </div>
    </div>
</x-app-layout>
