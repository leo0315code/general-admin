<x-app-layout>
    <x-slot name="header">
        <x-page-header title="日志详情 #{{ $log->id }}" description="登录审计与后台操作审计的完整记录" :breadcrumbs="[['label' => '操作日志', 'url' => route('logs.index')], ['label' => '日志详情']]">
            <x-slot name="actions">
                <a href="{{ route('logs.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    <span>返回列表</span>
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="card max-w-3xl">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">操作记录</h3>
        </div>

        <dl class="divide-y divide-gray-200 dark:divide-gray-700">
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">ID</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3">#{{ $log->id }}</dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">时间</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3">{{ $log->created_at?->format('Y-m-d H:i:s') }}</dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">操作人</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3">
                    {{ $log->username ?? '—' }}
                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">用户 ID: {{ $log->user_id ?? '—' }}</span>
                </dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">操作类型</dt>
                <dd class="text-sm sm:col-span-3">
                    <span class="inline-flex rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300 px-2.5 py-0.5 text-xs font-medium">
                        {{ $log->action }}
                    </span>
                </dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">模块 / 方法</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3">
                    <span class="font-mono text-xs">{{ $log->method ?? '—' }}</span>
                    <span class="mx-2 text-gray-400">/</span>
                    {{ $log->module ?? '—' }}
                </dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">描述</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3 whitespace-pre-wrap break-words">{{ $log->description ?? '—' }}</dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">IP 地址</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3 font-mono text-xs">{{ $log->ip ?? '—' }}</dd>
            </div>
            <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-4 gap-1 sm:gap-3">
                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:pt-1">User-Agent</dt>
                <dd class="text-sm text-gray-800 dark:text-gray-100 sm:col-span-3 break-words font-mono text-xs leading-relaxed">{{ $log->user_agent ?? '—' }}</dd>
            </div>
        </dl>
    </div>
</x-app-layout>
