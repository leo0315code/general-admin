<script setup>
// 状态徽章（与 Blade 的 <x-status-badge> 同配色、同 API）
// 统一「已发布/草稿、启用/停用」这类状态标签的样式，避免在各列表页重复硬编码。
// 用法：<StatusBadge type="success" icon="heroicon-o-check-circle">已发布</StatusBadge>
// type: primary | success | warning | danger | info | neutral
import Icon from './Icon.vue';

defineProps({
    // success | warning | danger | info | neutral
    type: { type: String, default: 'neutral' },
    icon: { type: String, default: '' },
    // sm | xs
    size: { type: String, default: 'sm' },
});

const styles = {
    primary: 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300',
    success: 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300',
    warning: 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
    danger: 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
    info: 'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300',
    neutral: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
};

const sizes = {
    sm: 'px-2.5 py-0.5 text-xs',
    xs: 'px-2 py-0.5 text-[11px]',
};

const cls = (type, size) =>
    `inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap ${styles[type] ?? styles.neutral} ${sizes[size] ?? sizes.sm}`;
</script>

<template>
    <span :class="cls(type, size)">
        <Icon v-if="icon" :name="icon" class="h-3.5 w-3.5" />
        <slot />
    </span>
</template>
