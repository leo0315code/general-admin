<x-app-layout>
    <x-slot name="header">
        <x-page-header title="仪表盘" description="欢迎回来，{{ Auth::user()->name }} 👋" />
    </x-slot>

    {{-- 统计卡片 --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6">
        {{-- 用户总数 --}}
        <div class="stat-card">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 shrink-0">
                <x-icon name="heroicon-o-users" class="h-6 w-6" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">用户总数</p>
                <p class="mt-0.5 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['users']) }}</p>
            </div>
        </div>

        {{-- 角色数 --}}
        <div class="stat-card">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 shrink-0">
                <x-icon name="heroicon-o-shield-check" class="h-6 w-6" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">角色数</p>
                <p class="mt-0.5 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['roles']) }}</p>
            </div>
        </div>

        {{-- 文章总数 --}}
        <div class="stat-card">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 shrink-0">
                <x-icon name="heroicon-o-document-text" class="h-6 w-6" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">文章总数</p>
                <p class="mt-0.5 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['posts']) }}</p>
            </div>
        </div>

        {{-- 已发布文章 --}}
        <div class="stat-card">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 shrink-0">
                <x-icon name="heroicon-o-check-badge" class="h-6 w-6" />
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">已发布文章</p>
                <p class="mt-0.5 text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['published_posts']) }}</p>
            </div>
        </div>
    </div>

    {{-- 最近文章 --}}
    <div class="mt-6 card">
        <div class="card-header flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">最近文章</h3>
            @can('post.manage')
                <a href="{{ route('posts.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                    查看全部
                    <x-icon name="heroicon-o-arrow-right" class="h-4 w-4" />
                </a>
            @endcan
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">标题</th>
                        <th class="th">作者</th>
                        <th class="th">状态</th>
                        <th class="th">发布时间</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($recentPosts as $post)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="td font-medium text-gray-900 dark:text-gray-100">{{ $post->title }}</td>
                            <td class="td text-gray-600 dark:text-gray-300">{{ $post->user->name ?? '—' }}</td>
                        <td class="td">
                            @if ($post->isPublished())
                                <x-status-badge type="success" icon="heroicon-o-check-circle">已发布</x-status-badge>
                            @else
                                <x-status-badge type="neutral" icon="heroicon-o-pencil-square">草稿</x-status-badge>
                            @endif
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ $post->published_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center">
                            <x-icon name="heroicon-o-document-text" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">暂无文章</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
