<x-guest-layout>
    <x-slot name="title">设置新密码</x-slot>

    {{-- 卡片 --}}
    <div class="card p-8 sm:p-10">
        {{-- 标题区 --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-amber-100 dark:bg-amber-500/15 mb-4">
                <x-icon name="heroicon-o-key" class="h-6 w-6 text-amber-500" />
            </div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">首次登录，请设置新密码</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">出于安全考虑，首次登录（或密码被重置后）需要先修改密码才能使用后台。</p>
        </div>

        <form method="POST" action="{{ route('password.setup.update') }}" class="space-y-5" x-data="{ showPassword: false }">
            @csrf

            {{-- 新密码 --}}
            <div>
                <label class="label" for="password">新密码</label>
                <div class="relative">
                    <x-icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    <input
                        id="password"
                        class="input pl-11 pr-11"
                        type="password"
                        name="password"
                        placeholder="至少 8 个字符"
                        required autofocus autocomplete="new-password"
                    >
                    <button type="button" @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-icon x-show="!showPassword" name="heroicon-o-eye" class="h-5 w-5" />
                        <x-icon x-show="showPassword" name="heroicon-o-eye-slash" class="h-5 w-5" x-cloak />
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            {{-- 确认新密码 --}}
            <div>
                <label class="label" for="password_confirmation">确认新密码</label>
                <div class="relative">
                    <x-icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    <input
                        id="password_confirmation"
                        class="input pl-11"
                        type="password"
                        name="password_confirmation"
                        placeholder="再次输入新密码"
                        required autocomplete="new-password"
                    >
                </div>
            </div>

            <button type="submit" class="btn-primary w-full justify-center">
                <x-icon name="heroicon-o-check" class="h-5 w-5" />
                设置密码并进入后台
            </button>
        </form>
    </div>
</x-guest-layout>
