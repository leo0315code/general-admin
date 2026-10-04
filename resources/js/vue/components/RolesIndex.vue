<script setup>
// 角色管理列表页 —— CRUD 样板推广（Vue 组件化）
// 表格由 Vue 渲染，分页由 Blade 渲染（GET 整页刷新）
// 删除复用 AppShell 全局 Vue ConfirmModal（app:confirm 事件 + 隐藏表单提交）
import { useConfirmAction } from '../composables/useConfirmAction.js';
import { useSortable } from '../composables/useSortable.js';
import EmptyState from './EmptyState.vue';
import Icon from './Icon.vue';
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    roleBase: { type: String, default: '' }, // 后台角色资源基址，如 /console/roles
    // 服务端按钮级权限：无权限时隐藏对应操作，避免「看得见点了 403」
    can: { type: Object, default: () => ({}) },
});

const { confirmAction } = useConfirmAction();
const { sortUrl, sortIcon } = useSortable(props);

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: '角色', sortable: true },
    { key: null, label: '描述' },
    { key: null, label: '权限数' },
    { key: null, label: '用户数' },
    { key: null, label: '操作', align: 'right' },
];

function destroyRole(role) {
    confirmAction({
        action: `${props.roleBase}/${role.id}`,
        method: 'DELETE',
        title: `确定要删除角色「${role.name}」吗？`,
        message: '删除后该角色及其权限分配将一并移除。',
    });
}
</script>

<template>
    <div>
        <!-- 角色表格 -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key ?? col.label"
                            class="th"
                            :class="col.align === 'right' ? 'text-right' : ''"
                        >
                            <a
                                v-if="col.sortable"
                                :href="sortUrl(col.key)"
                                class="th-sortable inline-flex items-center gap-1 group"
                            >
                                {{ col.label }}
                                <Icon :name="sortIcon(col.key)" class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                            </a>
                            <template v-else>{{ col.label }}</template>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ role.id }}</td>
                        <td class="td">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ role.name }}</span>
                                <StatusBadge
                                    v-if="role.is_admin"
                                    type="info"
                                    size="xs"
                                    icon="heroicon-o-star"
                                >
                                    超级管理员
                                </StatusBadge>
                            </div>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ role.description || '—' }}</td>
                        <td class="td">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200">
                                {{ role.permissions_count }}
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ role.users_count }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <a
                                    v-if="can.update"
                                    :href="`${roleBase}/${role.id}/edit`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="编辑"
                                >
                                    <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                </a>

                                <button
                                    v-if="can.destroy && !role.is_admin"
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="destroyRole(role)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="roles.length === 0">
                        <EmptyState :colspan="6" icon="heroicon-o-shield-check">
                            暂无角色
                        </EmptyState>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
