<script setup>
// 菜单类型徽章（目录/按钮/菜单）
//
// 背景：同一枚徽章此前在 MenusIndex 与 RoleForm 各写一份 `typeBadge()`，
// 且配色不一致——MenusIndex 用语义 token（primary/warning/success），
// RoleForm 用裸色（indigo/amber/emerald）。统一走 StatusBadge 的语义色。
//
// 用法：<MenuTypeBadge :type="row.type" :label="row.type_label" />
import StatusBadge from './StatusBadge.vue';

defineProps({
    // dir（目录） | button（按钮） | menu（菜单项）
    type: { type: String, default: 'menu' },
    label: { type: String, default: '' },
});

/** 菜单类型 → 徽章语义色 */
const STYLES = {
    dir: 'primary',
    button: 'warning',
    menu: 'success',
};
</script>

<template>
    <StatusBadge :type="STYLES[type] ?? STYLES.menu" size="xs">
        {{ label }}
    </StatusBadge>
</template>
