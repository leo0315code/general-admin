<script setup>
// 菜单管理列表页 —— CRUD 样板推广（Vue 组件化）
// 树形扁平渲染（深度缩进），无分页；删除/启停复用 AppShell 全局 Vue ConfirmModal
import Icon from './Icon.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    menusBase: { type: String, default: '' }, // 后台菜单资源基址，如 /console/menus
    can: { type: Object, default: () => ({ create: false, update: false, destroy: false }) },
});

function typeBadge(type) {
    if (type === 'dir') return 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300';
    if (type === 'button') return 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300';
    return 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300';
}

// 确认操作：构造隐藏表单 → AppShell 全局 Vue ConfirmModal
function confirmAction({ action, method, title, message, variant = 'danger' }) {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.innerHTML = `<input type="hidden" name="_token" value="${token}">`;
    if (method && method !== 'POST') {
        form.innerHTML += `<input type="hidden" name="_method" value="${method}">`;
    }
    document.body.appendChild(form);
    window.dispatchEvent(
        new CustomEvent('app:confirm', { detail: { form, title, message, variant } })
    );
}

function toggleStatus(menu) {
    confirmAction({
        action: `${props.menusBase}/${menu.id}/toggle-status`,
        method: 'PATCH',
        title: menu.status ? `确定要停用「${menu.title}」吗？` : `确定要启用「${menu.title}」吗？`,
        message: menu.status ? '停用后该菜单不会出现在侧边栏，但权限仍保留。' : '启用后该菜单恢复显示在侧边栏。',
        variant: 'primary',
    });
}

function destroyMenu(menu) {
    confirmAction({
        action: `${props.menusBase}/${menu.id}`,
        method: 'DELETE',
        title: `确定要删除「${menu.title}」吗？`,
        message: '对应权限记录将一并清理。',
    });
}
</script>

<template>
    <div>
        <!-- 菜单树表格（深度缩进） -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">名称</th>
                        <th class="th">权限标识</th>
                        <th class="th">路由</th>
                        <th class="th">排序</th>
                        <th class="th">状态</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="m in rows" :key="m.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td">
                            <div class="flex items-center gap-2" :style="{ paddingLeft: m.depth * 22 + 'px' }">
                                <span v-if="m.depth > 0" class="text-gray-300 dark:text-gray-600 select-none">└</span>
                                <Icon v-if="m.icon" :name="m.icon" class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ m.title }}</span>
                                <span
                                    :class="typeBadge(m.type)"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
                                >
                                    {{ m.type_label }}
                                </span>
                            </div>
                        </td>
                        <td class="td">
                            <code
                                v-if="m.permission_name"
                                class="text-xs font-mono px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200"
                            >
                                {{ m.permission_name }}
                            </code>
                            <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ m.route || '—' }}</td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ m.sort }}</td>
                        <td class="td">
                            <span
                                v-if="m.status"
                                class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2.5 py-0.5 text-xs bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300"
                            >
                                <Icon name="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                启用
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2.5 py-0.5 text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                <Icon name="heroicon-o-no-symbol" class="h-3.5 w-3.5" />
                                停用
                            </span>
                        </td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <a
                                    v-if="can.create && m.type !== 'button'"
                                    :href="`${menusBase}/create?pid=${m.id}`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                    :title="`在该节点下新增子节点`"
                                >
                                    <Icon name="heroicon-o-plus" class="h-4 w-4" />
                                </a>

                                <template v-if="can.update">
                                    <a
                                        :href="`${menusBase}/${m.id}/edit`"
                                        class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                        title="编辑"
                                    >
                                        <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                    </a>
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                        :title="m.status ? '停用' : '启用'"
                                        @click="toggleStatus(m)"
                                    >
                                        <Icon :name="m.status ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle'" class="h-4 w-4" />
                                    </button>
                                </template>

                                <button
                                    v-if="can.destroy"
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="destroyMenu(m)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="rows.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-rectangle-stack" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">还没有任何菜单节点</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
