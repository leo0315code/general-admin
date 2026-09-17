<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', '通用管理后台') }} · 419 会话已过期</title>
        @vite(['resources/css/app.css'])
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
    <body class="font-sans antialiased h-full bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-100">
        <div class="min-h-full flex flex-col items-center justify-center px-6 py-16">
            <div class="text-center max-w-md">
                <div class="inline-flex items-center justify-center h-20 w-20 rounded-2xl bg-violet-100 dark:bg-violet-500/15 mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-violet-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-extrabold tracking-tight text-gray-900 dark:text-white">419</h1>
                <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">会话已过期</h2>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    页面停留时间过长或登录状态已失效，请重新登录后继续操作。
                </p>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                        </svg>
                        重新登录
                    </a>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 transition">
                        返回首页
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>
