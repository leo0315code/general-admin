<!DOCTYPE html>
<html lang="zh-CN" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', '通用管理后台') }} · 500 服务器错误</title>
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
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <h1 class="text-6xl font-extrabold tracking-tight text-gray-900 dark:text-white">500</h1>
                <h2 class="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">服务器开小差了</h2>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    服务器遇到错误，无法完成您的请求。请稍后重试，或联系系统管理员排查。
                </p>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                        </svg>
                        重新加载
                    </a>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 transition">
                        返回首页
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>
