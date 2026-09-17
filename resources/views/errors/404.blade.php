<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', '通用管理后台') }} · 404 页面不存在</title>
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
                <div class="inline-flex items-center justify-center h-20 w-20 rounded-2xl bg-amber-100 dark:bg-amber-500/15 mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-10 w-10 text-amber-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-extrabold tracking-tight text-gray-900 dark:text-white">404</h1>
                <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">页面不存在</h2>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    您访问的页面不存在或已被移除，请检查地址是否正确。
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
