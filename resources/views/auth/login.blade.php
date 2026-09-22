<x-guest-layout>
    <x-slot name="title">登录</x-slot>

    @php
        // Vue 登录组件 props（@json 内不能直接写含括号的表达式，先构建变量）
        $loginProps = [
            'action' => route('login'),
            'captchaUrl' => route('captcha'),
            'csrf' => csrf_token(),
            'username' => old('username', ''),
            'errors' => $errors->toArray(),
            'status' => session('status', ''),
        ];
    @endphp

    {{-- 卡片 --}}
    <div class="card p-8 sm:p-10">
        {{-- 标题区 --}}
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">欢迎回来</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">登录到 {{ config('app.name', '通用管理后台') }}</p>
        </div>

        {{-- 登录表单（Vue 组件 LoginPage） --}}
        <div
            data-vue-app
            data-component="login"
            data-props='{!! vue_props($loginProps) !!}'
            x-ignore
        ></div>
    </div>
</x-guest-layout>
