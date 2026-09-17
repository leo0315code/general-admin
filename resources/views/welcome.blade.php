<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', '通用管理后台') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

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
    <body class="font-sans antialiased h-full bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-100">
        <div class="min-h-screen flex flex-col relative overflow-hidden">
            {{-- 背景装饰 --}}
            <div class="absolute -top-32 -right-32 h-[28rem] w-[28rem] rounded-full bg-indigo-200/40 dark:bg-indigo-600/10 blur-3xl"></div>
            <div class="absolute bottom-0 -left-24 h-80 w-80 rounded-full bg-violet-200/40 dark:bg-violet-600/10 blur-3xl"></div>

            {{-- 顶部导航 --}}
            <nav class="relative flex items-center justify-between px-6 sm:px-10 py-5">
                <div class="flex items-center gap-2.5">
                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white shadow-sm">
                        <x-icon name="heroicon-o-squares-2x2" class="h-5 w-5" />
                    </span>
                    <span class="text-lg font-bold tracking-tight">{{ config('app.name', '通用管理后台') }}</span>
                </div>
                <div>
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary">
                            <x-icon name="heroicon-o-arrow-right" class="h-4 w-4" />
                            进入后台
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary">
                            <x-icon name="heroicon-o-arrow-right-on-rectangle" class="h-4 w-4" />
                            登录
                        </a>
                    @endauth
                </div>
            </nav>

            {{-- 主内容 --}}
            <main class="relative flex-1 flex items-center justify-center px-6 py-16">
                <div class="max-w-2xl text-center">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 ring-1 ring-inset ring-indigo-200 dark:ring-indigo-500/30">
                        <x-icon name="heroicon-o-bolt" class="h-3.5 w-3.5" />
                        Laravel 13 · Tailwind CSS v4 · RBAC
                    </span>

                    <h1 class="mt-6 text-4xl sm:text-5xl font-bold tracking-tight leading-tight">
                        现代化通用管理后台
                    </h1>
                    <p class="mt-4 text-lg text-gray-500 dark:text-gray-400">
                        快速搭建、开箱即用的后台管理系统模板。<br class="hidden sm:block">
                        基于 spatie/laravel-permission 的权限体系，复制模板即可扩展业务模块。
                    </p>

                    <div class="mt-8 flex items-center justify-center gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary !px-6 !py-3 !text-base">
                                <x-icon name="heroicon-o-squares-2x2" class="h-5 w-5" />
                                进入后台
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary !px-6 !py-3 !text-base">
                                登录后台
                                <x-icon name="heroicon-o-arrow-right" class="h-5 w-5" />
                            </a>
                        @endauth
                    </div>
                </div>
            </main>

            {{-- 底部 --}}
            <footer class="relative px-6 sm:px-10 py-5 text-center text-sm text-gray-400 dark:text-gray-500">
                © {{ date('Y') }} {{ config('app.name', '通用管理后台') }}
            </footer>
        </div>
    </body>
</html>
