<x-guest-layout>
    <x-slot name="title">登录</x-slot>

    {{-- 卡片 --}}
    <div class="card p-8 sm:p-10">
        {{-- 标题区 --}}
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">欢迎回来</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">登录到 {{ config('app.name', '通用管理后台') }}</p>
        </div>

        {{-- 会话状态（如重置密码成功提示） --}}
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-5" x-data="{ showPassword: false }">
            @csrf

            {{-- 用户名 / 邮箱 --}}
            <div>
                <label class="label" for="username">用户名 / 邮箱</label>
                <div class="relative">
                    <x-icon name="heroicon-o-user" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    <input
                        id="username"
                        class="input pl-11"
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        placeholder="请输入用户名或邮箱"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>
                <x-input-error :messages="$errors->get('username')" class="mt-2" />
            </div>

            {{-- 密码（支持显示/隐藏切换） --}}
            <div>
                <label class="label" for="password">密码</label>
                <div class="relative">
                    <x-icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    <input
                        id="password"
                        x-ref="passwordInput"
                        class="input pl-11 pr-11"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        placeholder="请输入密码"
                        required
                        autocomplete="current-password"
                    >
                    {{-- 显示/隐藏切换 --}}
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                        :aria-label="showPassword ? '隐藏密码' : '显示密码'"
                        tabindex="-1"
                    >
                        <x-icon x-show="!showPassword" name="heroicon-o-eye" class="h-5 w-5" />
                        <x-icon x-show="showPassword" name="heroicon-o-eye-slash" class="h-5 w-5" x-cloak />
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            {{-- 验证码 --}}
            <div>
                <label class="label" for="captcha">验证码</label>
                <div class="flex items-end gap-3">
                    <div class="relative flex-1">
                        <x-icon name="heroicon-o-shield-exclamation" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                        <input
                            id="captcha"
                            class="input pl-11"
                            type="text"
                            name="captcha"
                            value="{{ old('captcha') }}"
                            placeholder="请输入验证码"
                            required
                            maxlength="8"
                            autocomplete="off"
                        >
                    </div>
                    <img
                        src="{{ route('captcha') }}"
                        alt="验证码"
                        title="看不清？点击刷新"
                        class="h-[42px] w-[120px] shrink-0 rounded-xl border border-gray-200 dark:border-gray-600 cursor-pointer select-none"
                        onclick="this.src = '{{ route('captcha') }}?t=' + Date.now()"
                    >
                </div>
                <x-input-error :messages="$errors->get('captcha')" class="mt-2" />
            </div>

            {{-- 记住我 --}}
            <div class="flex items-center justify-between">
                <label for="remember_me" class="inline-flex items-center cursor-pointer select-none">
                    <input
                        id="remember_me"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-700"
                        name="remember"
                    >
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">记住我</span>
                </label>
            </div>

            {{-- 登录按钮 --}}
            <button
                type="submit"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 active:from-indigo-700 active:to-violet-700 text-white text-sm font-semibold rounded-xl transition shadow-md shadow-indigo-500/20"
            >
                <x-icon name="heroicon-o-arrow-right-on-rectangle" class="h-4 w-4" />
                登录
            </button>
        </form>
    </div>
</x-guest-layout>
