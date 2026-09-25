<script setup>
// 密码确认表单（auth/confirm-password）——进入安全区域前要求再次输入密码
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    action: { type: String, default: '' },
    csrf: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
});

const password = ref('');
const show = ref(false);

function fieldError(field) {
    return props.errors[field] || [];
}
</script>

<template>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        这是后台的安全区域，请先输入您的密码确认身份后再继续。
    </div>

    <form :action="action" method="POST" class="space-y-4" novalidate>
        <input type="hidden" name="_token" :value="csrf">

        <div>
            <label class="label" for="password">密码</label>
            <div class="relative">
                <Icon name="heroicon-o-lock-closed" class="h-5 w-5 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                <input
                    id="password"
                    v-model="password"
                    name="password"
                    type="password"
                    class="input pl-11 pr-11"
                    :class="{ 'input-error': fieldError('password').length }"
                    placeholder="请输入当前密码"
                    required
                    autocomplete="current-password"
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
            <p v-for="e in fieldError('password')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <div class="flex justify-end mt-4">
            <button type="submit" class="btn-primary">
                确认密码
            </button>
        </div>
    </form>
</template>
