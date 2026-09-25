<x-app-layout>
    <x-slot name="header">
        <x-page-header title="操作日志" description="登录审计与后台操作审计" />
    </x-slot>

    <x-flash-messages />

    @php
        // 操作日志页 Vue 组件 props（筛选栏 + 只读表格由 Vue 渲染，分页保留 Blade）
        $logsIndexProps = [
            'keyword' => $keyword ?? '',
            'action' => $action ?? '',
            'date' => $date ?? '',
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
        ];
    @endphp

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
