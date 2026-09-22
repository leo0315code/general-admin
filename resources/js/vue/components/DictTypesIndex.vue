<script setup>
// 字典类型列表页 —— CRUD 样板推广（Vue 组件化）
// 表格 Vue 渲染，分页 Blade 渲染；删除复用 AppShell 全局 Vue ConfirmModal
import Icon from './Icon.vue';

const props = defineProps({
    dictTypes: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    typesBase: { type: String, default: '' }, // /console/dict-types
    itemsBase: { type: String, default: '' }, // /console/dict-items
});

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: '类型名称', sortable: true },
    { key: 'type', label: '类型标识', sortable: true },
    { key: null, label: '描述' },
    { key: null, label: '字典项' },
    { key: null, label: '状态' },
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

function destroyType(dt) {
    confirmAction({
        action: `${props.typesBase}/${dt.id}`,
        method: 'DELETE',
        title: `确定要删除类型「${dt.name}」及其全部字典项吗？`,
        message: '该类型下的所有字典项将一并删除，此操作不可恢复。',
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
                    <tr v-for="dt in dictTypes" :key="dt.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ dt.id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100">{{ dt.name }}</td>
                        <td class="td">
                            <span class="inline-flex px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-mono text-gray-600 dark:text-gray-300">{{ dt.type }}</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ dt.description || '—' }}</td>
                        <td class="td">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-info-100 dark:bg-info-500/20 text-xs font-medium text-info-700 dark:text-info-300">{{ dt.items_count }}</span>
                        </td>
                        <td class="td">
                            <span
                                v-if="dt.status"
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
                                    :href="`${itemsBase}?dict_type_id=${dt.id}`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                    title="字典项"
                                >
                                    <Icon name="heroicon-o-list-bullet" class="h-4 w-4" />
                                </a>
                                <a
                                    :href="`${typesBase}/${dt.id}/edit`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="编辑"
                                >
                                    <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                </a>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="destroyType(dt)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="dictTypes.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-bookmark-square" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">暂无字典类型</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
