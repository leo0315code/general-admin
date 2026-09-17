<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', '通用管理后台') }}@isset($title) - {{ $title }}@endisset</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- 防止暗色模式闪烁：在 CSS 加载前先根据 localStorage/系统偏好设置 .dark 类 --}}
        <script>
            (function () {
                const saved = localStorage.getItem('theme');
                const dark = saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
    </head>
    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-100">
        <div
            x-data="{ dark: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches), sidebarOpen: false }"
            x-effect="document.documentElement.classList.toggle('dark', dark); localStorage.setItem('theme', dark ? 'dark' : 'light');"
            class="min-h-screen"
        >
            {{-- 顶部导航栏（全宽，固定） --}}
            <header class="fixed top-0 inset-x-0 z-40 h-16 bg-white/90 dark:bg-gray-900/90 backdrop-blur border-b border-gray-200 dark:border-gray-700/80">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6">
                    {{-- 左侧：移动端汉堡按钮 + 品牌 --}}
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="sidebarOpen = !sidebarOpen"
                            class="inline-flex items-center justify-center p-2 rounded-lg text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 lg:hidden"
                            aria-label="切换侧边栏"
                        >
                            <x-icon name="heroicon-o-bars-3" class="h-6 w-6" />
                        </button>

                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 text-gray-800 dark:text-gray-100">
                            <span class="inline-flex items-center justify-center h-9 w-9 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                                <x-icon name="heroicon-o-squares-2x2" class="h-5 w-5" />
                            </span>
                            <span class="hidden sm:block font-semibold text-lg tracking-tight">{{ config('app.name', '通用管理后台') }}</span>
                        </a>
                    </div>

                    {{-- 右侧：搜索 + 明暗切换 + 用户下拉 --}}
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        {{-- 明/暗切换按钮 --}}
                        <button
                            type="button"
                            @click="dark = !dark"
                            class="inline-flex items-center justify-center p-2 rounded-lg text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                            :title="dark ? '切换到亮色模式' : '切换到暗色模式'"
                        >
                            <x-icon x-show="!dark" name="heroicon-o-moon" class="h-5 w-5" />
                            <x-icon x-show="dark" name="heroicon-o-sun" class="h-5 w-5" />
                        </button>

                        {{-- 用户下拉菜单 --}}
                        <x-dropdown align="right" width="56">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-2.5 pl-2 pr-1 py-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-white text-sm font-semibold">
                                        {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                                    </span>
                                    <span class="hidden sm:flex flex-col items-start leading-tight">
                                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ Auth::user()->name }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500">{{ Auth::user()->roles->pluck('name')->join(' / ') ?: '无角色' }}</span>
                                    </span>
                                    <x-icon name="heroicon-o-chevron-down" class="h-4 w-4 text-gray-400" />
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                    {{ Auth::user()->email }}
                                </div>
                                <x-dropdown-link :href="route('profile.edit')">
                                    <x-icon name="heroicon-o-user-circle" class="h-5 w-5 text-gray-400" />
                                    个人资料
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();">
                                        <x-icon name="heroicon-o-arrow-right-on-rectangle" class="h-5 w-5 text-gray-400" />
                                        退出登录
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </header>

            {{-- 移动端遮罩 --}}
            <div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden" x-cloak></div>

            {{-- 左侧边栏（固定；亮色为浅色，暗色为深色） --}}
            <aside
                :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                class="fixed left-0 top-16 bottom-0 z-30 w-64 bg-white dark:bg-slate-900 border-r border-gray-200 dark:border-slate-800 overflow-y-auto transition-transform duration-200 lg:translate-x-0"
            >
                @include('layouts.sidebar')
            </aside>

            {{-- 主内容区（全宽：预留顶栏高度与侧边栏宽度，不使用居中容器） --}}
            <main class="pt-16 lg:pl-64">
                <div class="p-4 sm:p-6 lg:p-8">
                    @isset($header)
                        <div class="mb-6">
                            {{ $header }}
                        </div>
                    @endisset

                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
