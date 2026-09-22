<script setup>
// 文章管理列表页 —— CRUD 样板推广（Vue 组件化）
// 搜索 + 状态筛选 + 勾选/批量删除 + 排序表头 + 行内发布/下线/删除
// 确认类操作复用 AppShell 全局 Vue ConfirmModal（app:confirm 事件 + 隐藏表单提交）
import { computed, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    keyword: { type: String, default: '' },
    status: { type: String, default: '' },
    posts: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    postBase: { type: String, default: '' }, // 后台文章资源基址，如 /console/posts
    routes: { type: Object, default: () => ({}) },
});

const search = ref(props.keyword || '');
const filterStatus = ref(props.status || '');
const selectedIds = ref([]);

const selectAll = computed(() => props.posts.length > 0 && selectedIds.value.length === props.posts.length);

function onSelectAll(e) {
    selectedIds.value = e.target.checked ? props.posts.map((p) => p.id) : [];
}

function onRowCheck(e, id) {
    if (e.target.checked) {
        if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((i) => i !== id);
    }
}

function buildQuery(extra = {}) {
    const q = new URLSearchParams();
    if (search.value.trim()) q.set('search', search.value.trim());
    if (filterStatus.value) q.set('status', filterStatus.value);
    for (const [k, v] of Object.entries(extra)) {
        if (v !== undefined && v !== null && v !== '') q.set(k, v);
    }
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
    { key: 'published_at', label: '发布时间', sortable: true },
    { key: null, label: '操作', align: 'right' },
];

// 确认操作：构造隐藏表单 → AppShell 全局 Vue ConfirmModal
function confirmAction({ action, method, extra, title, message, variant = 'danger' }) {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.innerHTML = `<input type="hidden" name="_token" value="${token}">`;
    if (method && method !== 'POST') {
        form.innerHTML += `<input type="hidden" name="_method" value="${method}">`;
    }
    for (const [k, v] of Object.entries(extra || {})) {
        if (Array.isArray(v)) {
            v.forEach((val) => (form.innerHTML += `<input type="hidden" name="${k}[]" value="${val}">`));
        } else {
            form.innerHTML += `<input type="hidden" name="${k}" value="${v}">`;
        }
    }
    document.body.appendChild(form);
    window.dispatchEvent(
        new CustomEvent('app:confirm', { detail: { form, title, message, variant } })
    );
}

function bulkDelete() {
    confirmAction({
        action: props.routes.bulk_delete,
        method: 'POST',
        extra: { ids: selectedIds.value },
        title: '确定删除选中的文章吗？',
        message: '删除后将进入回收站（软删除），可在回收站中还原。',
    });
}

function togglePublish(p) {
    confirmAction({
        action: `${props.postBase}/${p.id}/toggle-status`,
        method: 'PATCH',
        extra: {},
        title: p.is_published ? `确定要下线文章「${p.title}」吗？` : `确定要发布文章「${p.title}」吗？`,
        message: p.is_published ? '下线后文章将转为草稿，不再对外展示。' : '发布后文章将对读者可见。',
        variant: 'primary',
    });
}

function destroyPost(p) {
    confirmAction({
        action: `${props.postBase}/${p.id}`,
        method: 'DELETE',
        extra: {},
        title: `确定要删除文章「${p.title}」吗？`,
        message: '删除后将进入回收站（软删除），可在回收站中还原。',
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
                        id="posts-search"
                        type="search"
                        name="search"
                        placeholder="搜索文章标题…"
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

        <!-- 批量操作条 -->
        <div v-if="selectedIds.length > 0" class="px-5 pt-4">
            <div class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 dark:border-primary-500/30 bg-primary-50 dark:bg-primary-500/10 px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-700 dark:text-primary-300">
                    <Icon name="heroicon-o-check-circle" class="h-4 w-4" />
                    已选 <span class="font-bold">{{ selectedIds.length }}</span> 项
                </span>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-danger-ghost" @click="bulkDelete">
                        <Icon name="heroicon-o-trash" class="h-4 w-4" />
                        批量删除
                    </button>
                    <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" @click="selectedIds = []">
                        <Icon name="heroicon-o-x-mark" class="h-3.5 w-3.5" />
                        取消选择
                    </button>
                </div>
            </div>
        </div>

        <!-- 文章表格 -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th w-10">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                                :checked="selectAll"
                                @change="onSelectAll"
                                aria-label="全选"
                            >
                        </th>
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
                        <td class="td w-10">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                                :checked="selectedIds.includes(p.id)"
                                @change="onRowCheck($event, p.id)"
                                :aria-label="'选择文章 ' + p.title"
                            >
                        </td>
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
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ p.published_at || '—' }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <!-- 状态切换（发布/下线） -->
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg transition"
                                    :class="p.is_published
                                        ? 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'
                                        : 'text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10'"
                                    :title="p.is_published ? '下线' : '发布'"
                                    @click="togglePublish(p)"
                                >
                                    <Icon :name="p.is_published ? 'heroicon-o-eye-slash' : 'heroicon-o-eye'" class="h-4 w-4" />
                                </button>

                                <a
                                    :href="`${postBase}/${p.id}/edit`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="编辑"
                                >
                                    <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                </a>

                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="destroyPost(p)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="posts.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-document-text" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">没有找到文章</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
