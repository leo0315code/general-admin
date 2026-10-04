<script setup>
// 字典项列表页 —— CRUD 样板推广（Vue 组件化）
// 表格 Vue 渲染，分页 Blade 渲染；删除复用 AppShell 全局 Vue ConfirmModal
import Icon from './Icon.vue';
import StatusBadge from './StatusBadge.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    itemsBase: { type: String, default: '' }, // /console/dict-items
    // 服务端按钮级权限：无权限时隐藏对应操作，避免「看得见点了 403」
    can: { type: Object, default: () => ({}) },
    // 关闭表头排序链接（内嵌到字典类型编辑页时用：排序请到字典项列表页操作）
    sortable: { type: Boolean, default: true },
    // 附加到行内编辑链接的查询串（内嵌区块带上 redirect_to，编辑保存后回跳来源页）
    editQuery: { type: String, default: '' },
    // 删除后回跳的站内路径（空则回字典项列表页）
    redirectTo: { type: String, default: '' },
});

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: null, label: '名称' },
    { key: 'value', label: '值', sortable: true },
    { key: 'sort', label: '排序', sortable: true },
    { key: null, label: '状态' },
    { key: null, label: '备注' },
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

function hiddenInput(name, value) {
    const el = document.createElement('input');
    el.type = 'hidden';
    el.name = name;
    el.value = value;
    return el;
}

function confirmAction({ action, method, title, message, variant = 'danger' }) {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    // 用 DOM API 组装隐藏字段，避免字符串拼接带来的注入面
    form.appendChild(hiddenInput('_token', token));
    if (method && method !== 'POST') {
        form.appendChild(hiddenInput('_method', method));
    }
    if (props.redirectTo) {
        form.appendChild(hiddenInput('redirect_to', props.redirectTo));
    }
    document.body.appendChild(form);
    window.dispatchEvent(
        new CustomEvent('app:confirm', { detail: { form, title, message, variant } })
    );
}

function destroyItem(item) {
    confirmAction({
        action: `${props.itemsBase}/${item.id}`,
        method: 'DELETE',
        title: `确定要删除字典项「${item.label}」吗？`,
        message: '删除后该字典项将无法恢复。',
    });
}
</script>

<template>
    <div>
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
                                v-if="col.sortable && sortable"
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
                    <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ item.id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100">{{ item.label }}</td>
                        <td class="td">
                            <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-600 dark:text-gray-300">{{ item.value }}</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ item.sort }}</td>
                        <td class="td">
                            <StatusBadge
                                v-if="item.status"
                                type="success"
                                icon="heroicon-o-check-circle"
                            >
                                启用
                            </StatusBadge>
                            <StatusBadge v-else type="neutral" icon="heroicon-o-no-symbol">
                                停用
                            </StatusBadge>
                        </td>
                        <td class="td text-gray-500 dark:text-gray-400 max-w-[180px] truncate">{{ item.remark || '—' }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <a
                                    v-if="can.update"
                                    :href="`${itemsBase}/${item.id}/edit${editQuery}`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="编辑"
                                >
                                    <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                </a>
                                <button
                                    v-if="can.destroy"
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="destroyItem(item)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="items.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-list-bullet" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">该类型下暂无字典项</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
