<script setup>
// 文章回收站 —— Vue 组件化
// 搜索 + 状态筛选 + 表格渲染 + 还原/彻底删除（确认）；分页 Blade 渲染
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    keyword: { type: String, default: '' },
    status: { type: String, default: '' },
    posts: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    postBase: { type: String, default: '' }, // /console/posts（restore/force-delete 基于此）
});

const search = ref(props.keyword || '');
const filterStatus = ref(props.status || '');

function buildQuery() {
    const q = new URLSearchParams();
    if (search.value.trim()) q.set('search', search.value.trim());
    if (filterStatus.value) q.set('status', filterStatus.value);
    return q;
}

function submitFilter() {
    window.location.href = `${props.currentUrl}${buildQuery().toString() ? '?' + buildQuery().toString() : ''}`;
}

function clearFilter() {
    window.location.href = props.currentUrl;
}

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

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'title', label: '标题', sortable: true },
    { key: null, label: '作者' },
    { key: 'status', label: '状态', sortable: true },
    { key: 'created_at', label: '删除时间', sortable: true },
    { key: null, label: '操作', align: 'right' },
];

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

function restorePost(p) {
    confirmAction({
        action: `${props.postBase}/${p.id}/restore`,
        method: 'PATCH',
        title: `确定要还原文章「${p.title}」吗？`,
        message: '还原后文章将恢复显示。',
        variant: 'primary',
    });
}

function forceDestroy(p) {
    confirmAction({
        action: `${props.postBase}/${p.id}/force-delete`,
        method: 'DELETE',
        title: `彻底删除文章「${p.title}」？`,
        message: '彻底删除将无法恢复，确定继续吗？',
    });
}
</script>

<template>
    <div>
        <!-- 搜索栏 + 状态筛选 -->
        <div class="card-header">
            <form class="flex flex-col sm:flex-row gap-3" @submit.prevent="submitFilter">
                <div class="relative flex-1 sm:max-w-xs">
                    <Icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="search"
                        id="posts-trash-search"
                        type="search"
                        name="search"
                        placeholder="搜索已删除的标题…"
                        class="input pl-9"
                    >
                </div>
                <select v-model="filterStatus" name="status" class="input sm:w-40">
                    <option value="">全部状态</option>
                    <option value="draft">草稿</option>
                    <option value="published">已发布</option>
                </select>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">筛选</button>
                    <a v-if="keyword || status" href="#" class="btn-secondary" @click.prevent="clearFilter">清除</a>
                </div>
            </form>
        </div>

        <!-- 表格 -->
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
                    <tr v-for="p in posts" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ p.id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100 max-w-xs truncate">{{ p.title }}</td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ p.author || '—' }}</td>
                        <td class="td">
                            <span
                                v-if="p.is_published"
                                class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2.5 py-0.5 text-xs bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300"
                            >
                                <Icon name="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                已发布
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2.5 py-0.5 text-xs bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            >
                                <Icon name="heroicon-o-pencil-square" class="h-3.5 w-3.5" />
                                草稿
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ p.deleted_at }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="还原该文章"
                                    @click="restorePost(p)"
                                >
                                    <Icon name="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="彻底删除（不可恢复）"
                                    @click="forceDestroy(p)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="posts.length === 0">
                        <td colspan="6" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-document-text" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">回收站是空的</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
