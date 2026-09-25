<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', '通用管理后台') }}@isset($title) - {{ $title }}@endisset</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- 防止暗色模式闪烁：在 CSS 加载前先根据 localStorage/系统偏好设置 .dark 类
             （支持 ?theme=light / ?theme=dark 强制指定，便于预览） --}}
        <script>
            (function () {
                const urlTheme = new URLSearchParams(window.location.search).get('theme');
                const saved = urlTheme || localStorage.getItem('theme');
                const dark = saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (dark) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
    </head>
    <body class="font-sans antialiased h-full bg-gray-50 dark:bg-gray-950 text-gray-800 dark:text-gray-100">
        <div class="min-h-screen flex flex-col lg:flex-row">
            {{-- 左侧品牌区（深色渐变，桌面端显示） --}}
            <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between p-12 relative overflow-hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 text-white">
                {{-- 背景装饰 --}}
                <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute bottom-0 -left-16 h-72 w-72 rounded-full bg-violet-400/20 blur-2xl"></div>

                {{-- 顶部品牌 --}}
                <div class="relative flex items-center gap-3">
                    <span class="inline-flex items-center justify-center h-11 w-11 rounded-xl bg-white/15 backdrop-blur ring-1 ring-white/20">
                        <x-icon name="heroicon-o-squares-2x2" class="h-6 w-6" />
                    </span>
                    <span class="text-xl font-bold tracking-tight">{{ config('app.name', '通用管理后台') }}</span>
                </div>

                {{-- 中间标语 --}}
                <div class="relative max-w-md">
                    <h2 class="text-4xl font-bold leading-tight tracking-tight">
                        现代化、快速、可复用的<br>通用管理后台
                    </h2>
                    <p class="mt-4 text-lg text-indigo-100/80">
                        Laravel + Tailwind CSS + RBAC，开箱即用。
                    </p>

                    {{-- 特性列表 --}}
                    <ul class="mt-8 space-y-3">
                        <li class="flex items-center gap-3 text-indigo-50/90">
                            <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-white/15">
                                <x-icon name="heroicon-o-shield-check" class="h-4 w-4" />
                            </span>
                            基于 spatie/laravel-permission 的 RBAC 权限体系
                        </li>
                        <li class="flex items-center gap-3 text-indigo-50/90">
                            <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-white/15">
                                <x-icon name="heroicon-o-moon" class="h-4 w-4" />
                            </span>
                            明暗双主题，跟随系统自动切换
                        </li>
                        <li class="flex items-center gap-3 text-indigo-50/90">
                            <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-white/15">
                                <x-icon name="heroicon-o-bolt" class="h-4 w-4" />
                            </span>
                            CRUD 模板化，快速扩展业务模块
                        </li>
                    </ul>
                </div>

                {{-- 底部 --}}
                <div class="relative text-sm text-indigo-200/70">© {{ date('Y') }} {{ config('app.name', '通用管理后台') }}</div>
            </div>

            {{-- 右侧表单区 --}}
            <div class="flex-1 flex flex-col justify-center px-6 py-12 sm:px-12 lg:px-16">
                {{-- 移动端品牌 --}}
                <div class="lg:hidden flex items-center justify-center gap-2.5 mb-10">
                    <span class="inline-flex items-center justify-center h-10 w-10 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-white">
                        <x-icon name="heroicon-o-squares-2x2" class="h-5 w-5" />
                    </span>
                    <span class="text-lg font-bold tracking-tight">{{ config('app.name', '通用管理后台') }}</span>
                </div>

                <div class="w-full max-w-md mx-auto">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
