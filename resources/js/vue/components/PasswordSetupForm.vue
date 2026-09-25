<script setup>
// 首次登录强制改密表单（auth/password-setup）
// 密码规则与后端 Password::defaults 一致（至少 10 位，含字母与数字）
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    action: { type: String, default: '' },
    csrf: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
});

const password = ref('');
const confirmation = ref('');
const show = ref(false);

function fieldError(field) {
    return props.errors[field] || [];
}
</script>

<template>
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-amber-100 dark:bg-amber-500/15 mb-4">
            <Icon name="heroicon-o-lock-closed" class="h-6 w-6 text-amber-500" />
        </div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 tracking-tight">首次登录，请设置新密码</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">出于安全考虑，首次登录（或密码被重置后）需要先修改密码才能使用后台。</p>
    </div>

    <form :action="action" method="POST" class="space-y-5" novalidate>
        <input type="hidden" name="_token" :value="csrf">

        <div>
            <label class="label" for="password">新密码</label>
            <div class="relative">
                <Icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                    id="password"
                    v-model="password"
                    name="password"
                    type="password"
                    class="input pl-11 pr-11"
                    :class="{ 'input-error': fieldError('password').length }"
                    placeholder="至少 10 位，含字母与数字"
                    required
                    autofocus
                    autocomplete="new-password"
                >
                <button
                    type="button"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                    :title="show ? '隐藏密码' : '显示密码'"
                    @click="show = !show"
                >
                    <Icon v-if="!show" name="heroicon-o-eye" class="h-5 w-5" />
                    <Icon v-else name="heroicon-o-eye-slash" class="h-5 w-5" />
                </button>
            </div>
            <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">至少 10 位，需同时包含字母与数字</p>
            <p v-for="e in fieldError('password')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <div>
            <label class="label" for="password_confirmation">确认新密码</label>
            <div class="relative">
                <Icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                    id="password_confirmation"
                    v-model="confirmation"
                    name="password_confirmation"
                    type="password"
                    class="input pl-11"
                    :class="{ 'input-error': fieldError('password_confirmation').length }"
                    placeholder="再次输入新密码"
                    required
                    autocomplete="new-password"
                >
            </div>
            <p v-for="e in fieldError('password_confirmation')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <button type="submit" class="btn-primary w-full justify-center">
            <Icon name="heroicon-o-check" class="h-5 w-5" />
            设置密码并进入后台
        </button>
    </form>
</template>
