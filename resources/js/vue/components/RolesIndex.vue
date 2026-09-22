<script setup>
// 角色管理列表页 —— CRUD 样板推广（Vue 组件化）
// 表格由 Vue 渲染，分页由 Blade 渲染（GET 整页刷新）
// 删除复用 AppShell 全局 Vue ConfirmModal（app:confirm 事件 + 隐藏表单提交）
import Icon from './Icon.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    roleBase: { type: String, default: '' }, // 后台角色资源基址，如 /console/roles
});

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: '角色', sortable: true },
    { key: null, label: '描述' },
    { key: null, label: '权限数' },
    { key: null, label: '用户数' },
    { key: null, label: '操作', align: 'right' },
];

function sortUrl(key) {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(props.query)) {
        if (k === 'sort' || k === 'sort_dir' || k === 'page') continue;
        if (v !== undefined && v !== null && v !== '') q.append(k, v);
    }
    q.set('sort', key);
    q.set('sort_dir', props.sort === key && props.sortDir === 'asc' ? 'desc' : 'asc');
    q.set('page', '1');
    return `${props.currentUrl}?${q.toString()}`;
}

function sortIcon(key) {
    if (props.sort === key) return props.sortDir === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down';
    return 'heroicon-o-chevron-up-down';
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
                                <span
                                    v-if="role.is_admin"
                                    class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2 py-0.5 text-[11px] bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300"
                                >
                                    <Icon name="heroicon-o-star" class="h-3 w-3" />
                                    超级管理员
                                </span>
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
                                    :href="`${roleBase}/${role.id}/edit`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="编辑"
                                >
                                    <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                </a>

                                <button
                                    v-if="!role.is_admin"
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
                        <td colspan="6" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-shield-check" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">暂无角色</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
