<script setup>
// 用户回收站 —— Vue 组件化
// 搜索 + 表格渲染 + 还原/彻底删除（确认）；分页 Blade 渲染
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    keyword: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    userBase: { type: String, default: '' }, // /console/users（restore/force-delete 基于此）
});

const search = ref(props.keyword || '');

function submitSearch() {
    const q = new URLSearchParams();
    if (search.value.trim()) q.set('search', search.value.trim());
    if (props.sort) {
        q.set('sort', props.sort);
        q.set('sort_dir', props.sortDir);
    }
    window.location.href = `${props.currentUrl}${q.toString() ? '?' + q.toString() : ''}`;
}

function clearSearch() {
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
    { key: 'name', label: '姓名', sortable: true },
    { key: 'email', label: '邮箱', sortable: true },
    { key: null, label: '角色' },
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

function restoreUser(u) {
    confirmAction({
        action: `${props.userBase}/${u.id}/restore`,
        method: 'PATCH',
        title: `确定要还原用户「${u.name}」吗？`,
        message: '还原后该用户可正常登录。',
        variant: 'primary',
    });
}

function forceDestroy(u) {
    confirmAction({
        action: `${props.userBase}/${u.id}/force-delete`,
        method: 'DELETE',
        title: `彻底删除用户「${u.name}」？`,
        message: '彻底删除将无法恢复，确定继续吗？',
    });
}
</script>

<template>
    <div>
        <!-- 搜索栏 -->
        <div class="card-header">
            <form class="flex flex-col sm:flex-row gap-3" @submit.prevent="submitSearch">
                <div class="relative flex-1 sm:max-w-xs">
                    <Icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="search"
                        id="users-trash-search"
                        type="search"
                        name="search"
                        placeholder="搜索已删除的姓名或邮箱…"
                        class="input pl-9"
                    >
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">搜索</button>
                    <a v-if="keyword" href="#" class="btn-secondary" @click.prevent="clearSearch">清除</a>
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
                    <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ u.id }}</td>
                        <td class="td font-medium text-gray-900 dark:text-gray-100">{{ u.name }}</td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ u.email }}</td>
                        <td class="td">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {{ u.roles || '无角色' }}
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ u.deleted_at }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="还原该用户"
                                    @click="restoreUser(u)"
                                >
                                    <Icon name="heroicon-o-arrow-uturn-left" class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="彻底删除（不可恢复）"
                                    @click="forceDestroy(u)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="users.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-trash" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">回收站是空的</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
