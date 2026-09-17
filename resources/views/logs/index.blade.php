<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">操作日志</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">登录审计与后台操作审计</p>
            </div>
        </div>
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">ID</th>
                        <th class="th">时间</th>
                        <th class="th">用户</th>
                        <th class="th">操作类型</th>
                        <th class="th">描述</th>
                        <th class="th">IP 地址</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td text-gray-500 dark:text-gray-400">{{ $log->id }}</td>
                            <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                            <td class="td">
                                <span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-200">
                                    @if ($log->user_id)
                                        <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 text-[10px] font-semibold">
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
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">
                                        <x-icon name="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                        {{ $log->action }}
                                    </span>
                                @elseif (str_contains($log->action, '失败'))
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300">
                                        <x-icon name="heroicon-o-x-circle" class="h-3.5 w-3.5" />
                                        {{ $log->action }}
                                    </span>
                                @elseif ($log->action === '删除')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400">
                                        <x-icon name="heroicon-o-trash" class="h-3.5 w-3.5" />
                                        {{ $log->action }}
                                    </span>
                                @elseif ($log->action === '创建')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                                        <x-icon name="heroicon-o-plus-circle" class="h-3.5 w-3.5" />
                                        {{ $log->action }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $log->action }}
                                    </span>
                                @endif
                            </td>
                            <td class="td text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ $log->description ?? '—' }}</td>
                            <td class="td text-gray-500 dark:text-gray-400 font-mono text-xs">{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <x-icon name="heroicon-o-clipboard-document-list" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">暂无操作日志</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- 分页 --}}
        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
