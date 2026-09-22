<script setup>
// 通用 Modal（Vue 页面内使用）
// 用法：<Modal v-model:show="open" maxWidth="md"><div class="p-6">...</div></Modal>
import { watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: '2xl' }, // sm | md | lg | xl | 2xl
});

const emit = defineEmits(['update:show']);

const widths = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
};

const maxW = widths[props.maxWidth] || widths['2xl'];

watch(
    () => props.show,
    (v) => document.body.classList.toggle('overflow-y-hidden', v),
    { immediate: true }
);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-gray-500 dark:bg-gray-900 opacity-75" @click="emit('update:show', false)"></div>
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full sm:mx-auto" :class="maxW">
                <slot />
            </div>
        </div>
    </Teleport>
</template>
