<x-app-layout>
    <x-slot name="header">
        <x-page-header title="操作日志" description="登录审计与后台操作审计" />
    </x-slot>

    <x-flash-messages />

    <div class="card">
        {{-- 筛选栏 --}}
        <div class="card-header">
            <form method="GET" action="{{ route('logs.index') }}" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <x-icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="search" name="search" value="{{ $keyword }}" placeholder="搜索用户名 / 描述 / IP…" class="input pl-9">
                </div>
                <select name="action" class="input sm:w-44">
                    <option value="">全部操作类型</option>
                    @foreach ($actionOptions as $opt)
                        <option value="{{ $opt }}" @selected($action === $opt)>{{ $opt }}</option>
                    @endforeach
                </select>
                <input type="date" name="date" value="{{ $date }}" class="input sm:w-44" title="按日期筛选">
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">筛选</button>
                    @if ($keyword !== '' || $action || $date)
                        <a href="{{ route('logs.index') }}" class="btn-secondary">清除</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- 日志表格 --}}
        <x-data-table
            :columns="[
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'created_at', 'label' => '时间', 'sortable' => true],
                ['key' => null, 'label' => '用户'],
                ['key' => 'action', 'label' => '操作类型', 'sortable' => true],
                ['key' => null, 'label' => '描述'],
                ['key' => 'ip', 'label' => 'IP 地址', 'sortable' => true],
            ]"
            :sort="$sort ?? null"
            :sort-dir="$dir ?? 'desc'"
        >
            <x-slot name="rows">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ $log->id }}</td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="td">
                            <span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-200">
                                @if ($log->user_id)
                                    <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-[10px] font-semibold">
                                        {{ strtoupper(mb_substr($log->username ?? '?', 0, 1)) }}
                                    </span>
                                @else
                                    <x-icon name="heroicon-o-user-minus" class="h-4 w-4 text-gray-400" />
                                @endif
                                {{ $log->username ?? '—' }}
                            </span>
                        </td>
                        <td class="td">
                            @if ($log->action === '登录' || $log->action === '登录成功')
                                <x-status-badge type="success" icon="heroicon-o-check-circle">{{ $log->action }}</x-status-badge>
                            @elseif (str_contains($log->action, '失败'))
                                <x-status-badge type="danger" icon="heroicon-o-x-circle">{{ $log->action }}</x-status-badge>
                            @elseif ($log->action === '删除')
                                <x-status-badge type="danger" icon="heroicon-o-trash">{{ $log->action }}</x-status-badge>
                            @elseif ($log->action === '创建')
                                <x-status-badge type="info" icon="heroicon-o-plus-circle">{{ $log->action }}</x-status-badge>
                            @else
                                <x-status-badge type="neutral">{{ $log->action }}</x-status-badge>
                            @endif
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ $log->description ?? '—' }}</td>
                        <td class="td text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $log->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <x-empty-state icon="heroicon-o-clipboard-document-list" title="暂无操作日志" :colspan="6" />
                @endforelse
            </x-slot>
        </x-data-table>

        {{-- 分页 + 每页条数 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$logs" />
                <x-pagination :paginator="$logs" />
            </div>
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
