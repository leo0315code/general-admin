<script setup>
// 登录表单 —— Vue 组件化样板
// Blade 端通过 data-vue-app 挂载，props 由 data-props 注入
import { reactive, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    action: { type: String, default: '' },
    captchaUrl: { type: String, default: '' },
    csrf: { type: String, default: '' },
    username: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    status: { type: String, default: '' },
});

const loading = ref(false);
const showPassword = ref(false);

// 双向绑定表单状态（v-model）：避免单向 :value 在提交瞬间重渲染时
// 把用户输入重置回 props 初值，导致提交空字段
const form = reactive({
    username: props.username,
    password: '',
    captcha: '',
    remember: false,
});

// 只在浏览器约束校验通过、submit 真正触发后才进入 loading，
// 避免验证码等必填项为空时按钮被锁死转圈
function handleSubmit() {
    loading.value = true;
}

function refreshCaptcha(e) {
    const img = e.currentTarget;
    img.src = `${props.captchaUrl}?t=${Date.now()}`;
}

function fieldError(name) {
    return props.errors?.[name]?.[0] || '';
}
</script>

<template>
    <form method="POST" :action="action" class="space-y-5" @submit="handleSubmit">
        <input type="hidden" name="_token" :value="csrf" />

        <!-- 会话状态（如重置密码成功提示） -->
        <div
            v-if="status"
            class="rounded-xl bg-success-50 dark:bg-success-500/10 border border-success-200 dark:border-success-500/30 px-4 py-3 text-sm text-success-700 dark:text-success-300"
        >
            {{ status }}
        </div>

        <!-- 用户名 / 邮箱 -->
        <div>
            <label for="username" class="label">
                用户名 / 邮箱 <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
            </label>
            <div class="relative">
                <Icon name="heroicon-o-user" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                    id="username"
                    name="username"
                    type="text"
                    class="input pl-11"
                    :class="{ 'input-error': fieldError('username') }"
                    v-model="form.username"
                    placeholder="请输入用户名或邮箱"
                    required
                    autofocus
                    autocomplete="username"
                >
            </div>
            <p v-if="fieldError('username')" class="field-error-text" role="alert">{{ fieldError('username') }}</p>
        </div>

        <!-- 密码（支持显示/隐藏切换） -->
        <div>
            <label for="password" class="label">
                密码 <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
            </label>
            <div class="relative">
                <Icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                    id="password"
                    name="password"
                    class="input pl-11 pr-11"
                    :class="{ 'input-error': fieldError('password') }"
                    :type="showPassword ? 'text' : 'password'"
                    v-model="form.password"
                    placeholder="请输入密码"
                    required
                    autocomplete="current-password"
                >
                <button
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition"
                    :aria-label="showPassword ? '隐藏密码' : '显示密码'"
                    tabindex="-1"
                    @click="showPassword = !showPassword"
                >
                    <Icon v-if="!showPassword" name="heroicon-o-eye" class="h-5 w-5" />
                    <Icon v-else name="heroicon-o-eye-slash" class="h-5 w-5" />
                </button>
            </div>
            <p v-if="fieldError('password')" class="field-error-text" role="alert">{{ fieldError('password') }}</p>
        </div>

        <!-- 验证码 -->
        <div>
            <label for="captcha" class="label">
                验证码 <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
            </label>
            <div class="flex items-end gap-3">
                <div class="relative flex-1">
                    <Icon name="heroicon-o-shield-exclamation" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                    <input
                        id="captcha"
                        name="captcha"
                        type="text"
                        class="input pl-11"
                        :class="{ 'input-error': fieldError('captcha') }"
                        v-model="form.captcha"
                        placeholder="请输入验证码"
                        required
                        maxlength="8"
                        autocomplete="off"
                    >
                </div>
                <img
                    :src="captchaUrl"
                    alt="验证码"
                    title="看不清？点击刷新"
                    class="h-[42px] w-[120px] shrink-0 rounded-xl border border-gray-200 dark:border-gray-600 cursor-pointer select-none"
                    @click="refreshCaptcha"
                >
            </div>
            <p v-if="fieldError('captcha')" class="field-error-text" role="alert">{{ fieldError('captcha') }}</p>
        </div>

        <!-- 记住我 -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center cursor-pointer select-none">
                <input
                    id="remember_me"
                    type="checkbox"
                    class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-700"
                    name="remember"
                    value="1"
                    v-model="form.remember"
                >
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-300">记住我</span>
            </label>
        </div>

        <!-- 登录按钮（含提交 loading 态） -->
        <button
            type="submit"
            :disabled="loading"
            :class="loading ? 'opacity-70 cursor-wait' : ''"
            class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-primary-600 to-violet-600 hover:from-primary-500 hover:to-violet-500 active:from-primary-700 active:to-violet-700 text-white text-sm font-semibold rounded-xl transition shadow-md shadow-primary-500/20"
        >
            <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span v-if="loading">登录中…</span>
            <span v-else class="inline-flex items-center gap-2">
                <Icon name="heroicon-o-arrow-right-on-rectangle" class="h-4 w-4" />
                登录
            </span>
        </button>
    </form>
</template>
