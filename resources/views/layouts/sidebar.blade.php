{{-- 左侧边栏菜单（亮色浅色 / 暗色深色；权限控制显隐；用户信息在顶栏下拉展示） --}}
<div class="flex flex-col h-full py-4">
    <nav class="flex-1 px-3 space-y-0.5">
        {{-- 概览分组 --}}
        <div class="px-3 pb-1.5 pt-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">概览</div>

        @can('dashboard.view')
            <a
                href="{{ route('dashboard') }}"
                class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
            >
                <x-icon name="heroicon-o-squares-2x2" class="h-5 w-5 shrink-0" />
                仪表盘
            </a>
        @endcan

        {{-- 内容管理分组 --}}
        @can('post.manage')
            <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">内容管理</div>
            <a
                href="{{ route('posts.index') }}"
                class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('posts.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
            >
                <x-icon name="heroicon-o-document-text" class="h-5 w-5 shrink-0" />
                文章管理
            </a>
        @endcan

        {{-- 系统管理分组 --}}
        @if (Auth::user()->can('user.manage') || Auth::user()->can('role.manage') || Auth::user()->can('log.manage') || Auth::user()->can('dict.manage') || Auth::user()->can('settings.manage'))
            <div class="px-3 pb-1.5 pt-4 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">系统管理</div>

            @can('user.manage')
                <a
                    href="{{ route('users.index') }}"
                    class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-icon name="heroicon-o-users" class="h-5 w-5 shrink-0" />
                    用户管理
                </a>
            @endcan

            @can('role.manage')
                <a
                    href="{{ route('roles.index') }}"
                    class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('roles.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-icon name="heroicon-o-shield-check" class="h-5 w-5 shrink-0" />
                    角色管理
                </a>
            @endcan

            @can('log.manage')
                <a
                    href="{{ route('logs.index') }}"
                    class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('logs.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-icon name="heroicon-o-clipboard-document-list" class="h-5 w-5 shrink-0" />
                    操作日志
                </a>
            @endcan

            @can('dict.manage')
                <a
                    href="{{ route('dict-types.index') }}"
                    class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('dict-*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-icon name="heroicon-o-bookmark-square" class="h-5 w-5 shrink-0" />
                    数据字典
                </a>
            @endcan

            @if (Auth::user()->hasRole(\App\Models\User::ROLE_ADMIN))
                <a
                    href="{{ route('settings.index') }}"
                    class="relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ request()->routeIs('settings.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' : 'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' }}"
                >
                    <x-icon name="heroicon-o-cog-6-tooth" class="h-5 w-5 shrink-0" />
                    系统设置
                </a>
            @endif
        @endif
    </nav>

    {{-- 底部：退出登录 --}}
    <div class="px-3 mt-4 pt-4 border-t border-gray-200 dark:border-slate-800">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white transition"
            >
                <x-icon name="heroicon-o-arrow-right-on-rectangle" class="h-5 w-5 shrink-0" />
                退出登录
            </button>
        </form>
    </div>
</div>
