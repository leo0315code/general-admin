<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', '通用管理后台') }} · 403 无权访问</title>
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
                <div class="inline-flex items-center justify-center h-20 w-20 rounded-2xl bg-red-100 dark:bg-red-500/15 mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-red-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
                <h1 class="text-6xl font-extrabold tracking-tight text-gray-900 dark:text-white">403</h1>
                <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">无权访问</h2>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    {{ $exception?->getMessage() ?: '您没有权限访问该页面，请联系管理员分配相应权限。' }}
                </p>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                        </svg>
                        返回首页
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 transition">
                        返回登录页
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>
