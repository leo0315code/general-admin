<script setup>
// 提交按钮（Vue 版，对应 Blade x-submit-button）
// loading 仅在浏览器约束校验通过后开启，避免必填为空时按钮被锁死转圈
import { nextTick, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    label: { type: String, default: '提交' },
    icon: { type: String, default: null },
    loadingText: { type: String, default: '提交中…' },
    variant: { type: String, default: 'primary' }, // primary | danger | secondary
    type: { type: String, default: 'submit' },
});

const loading = ref(false);
const btn = ref(null);

const variantClass = {
    primary: 'btn-primary',
    danger: 'inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-danger-600 hover:bg-danger-500 active:bg-danger-700 text-white text-sm font-medium rounded-control transition shadow-card',
    secondary: 'btn-secondary',
}[props.variant];

function handleClick() {
    nextTick(() => {
        const form = btn.value?.form;
        if (!form || form.checkValidity()) loading.value = true;
    });
}
</script>

<template>
    <button
        ref="btn"
        :type="type"
        :disabled="loading"
        :class="[variantClass, loading ? 'opacity-70 cursor-wait' : '']"
        @click="handleClick"
    >
        <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span v-if="loading">{{ loadingText }}</span>
        <span v-else class="inline-flex items-center gap-1.5">
            <Icon v-if="icon" :name="icon" class="h-4 w-4" />
            {{ label }}
        </span>
    </button>
</template>
