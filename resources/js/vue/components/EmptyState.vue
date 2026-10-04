<script setup>
// 表格空状态行（列表页无数据时的占位）
// 此前 10 个列表页各写一份 `<td colspan="N" class="px-6 py-12 text-center">…</td>`，
// 只有图标与文案不同，改样式要逐页改。
// 用法：<EmptyState :colspan="columns.length + 1" icon="heroicon-o-user">没有找到用户</EmptyState>
import Icon from './Icon.vue';

defineProps({
    // 表格列数（含操作列、勾选列）
    colspan: { type: Number, default: 1 },
    icon: { type: String, default: '' },
});
</script>

<template>
    <td :colspan="colspan" class="px-6 py-12 text-center">
        <Icon v-if="icon" :name="icon" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
        <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">
            <slot />
        </p>
    </td>
</template>
