<script setup>
// 用户管理列表页 —— CRUD 样板（Vue 组件化）
// Blade 端 data-vue-app 挂载；表格由 Vue 渲染，分页由 Blade 渲染（GET 整页刷新）
// 确认类操作复用 AppShell 全局 Vue ConfirmModal（window app:confirm 事件 + 隐藏表单提交）
import { computed, ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    keyword: { type: String, default: '' },
    users: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    userBase: { type: String, default: '' }, // 后台用户资源基址，如 /console/users
    canManage: { type: Boolean, default: false },
    canDestroy: { type: Boolean, default: false },
    routes: { type: Object, default: () => ({}) },
});

const search = ref(props.keyword || '');
const selectedIds = ref([]);

const selectAll = computed(() => props.users.length > 0 && selectedIds.value.length === props.users.length);

function onSelectAll(e) {
    selectedIds.value = e.target.checked ? props.users.map((u) => u.id) : [];
}

function onRowCheck(e, id) {
    if (e.target.checked) {
        if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
    } else {
        selectedIds.value = selectedIds.value.filter((i) => i !== id);
    }
}

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
    { key: 'status', label: '状态', sortable: true },
    { key: 'last_login_at', label: '最后登录', sortable: true },
    { key: 'created_at', label: '注册时间', sortable: true },
    { key: null, label: '操作', align: 'right' },
];

function initial(name) {
    return name ? name.charAt(0).toUpperCase() : '?';
}

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
        title: '确定删除选中的用户吗？',
        message: '删除后将进入回收站（软删除），可在回收站中还原。',
    });
}

function bulkToggle() {
    confirmAction({
        action: props.routes.bulk_toggle,
        method: 'POST',
        extra: { ids: selectedIds.value },
        title: '确定启停选中的用户吗？',
        message: '停用的用户将无法登录。',
        variant: 'primary',
    });
}

function toggleStatus(u) {
    confirmAction({
        action: `${props.userBase}/${u.id}/toggle-status`,
        method: 'PATCH',
        extra: {},
        title: `确定要${u.status ? '停用' : '启用'}用户「${u.name}」吗？`,
        message: u.status ? '停用后该用户将无法登录。' : '启用后该用户可正常登录。',
    });
}

function destroyUser(u) {
    confirmAction({
        action: `${props.userBase}/${u.id}`,
        method: 'DELETE',
        extra: {},
        title: `确定要删除用户「${u.name}」吗？`,
        message: '删除后将无法登录（软删除，可在回收站中还原）。',
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
                        id="users-search"
                        type="search"
                        name="search"
                        placeholder="搜索姓名或邮箱…"
                        class="input pl-9"
                    >
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">搜索</button>
                    <a v-if="keyword" href="#" class="btn-secondary" @click.prevent="clearSearch">清除</a>
                </div>
            </form>
        </div>

        <!-- 批量操作条 -->
        <div v-if="canManage && selectedIds.length > 0" class="px-5 pt-4">
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
                    <button type="button" class="btn-ghost" @click="bulkToggle">
                        <Icon name="heroicon-o-arrow-path" class="h-4 w-4" />
                        批量启停
                    </button>
                    <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" @click="selectedIds = []">
                        <Icon name="heroicon-o-x-mark" class="h-3.5 w-3.5" />
                        取消选择
                    </button>
                </div>
            </div>
        </div>

        <!-- 用户表格 -->
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
                    <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td w-10">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                                :checked="selectedIds.includes(u.id)"
                                @change="onRowCheck($event, u.id)"
                                :aria-label="'选择用户 ' + u.name"
                            >
                        </td>
                        <td class="td text-gray-500 dark:text-gray-400">{{ u.id }}</td>
                        <td class="td">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-semibold shrink-0">
                                    {{ initial(u.name) }}
                                </span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ u.name }}</span>
                            </div>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300">{{ u.email }}</td>
                        <td class="td">
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="r in u.roles"
                                    :key="r"
                                    class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2 py-0.5 text-[11px] bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300"
                                >
                                    <Icon :name="r === 'admin' ? 'heroicon-o-shield-check' : 'heroicon-o-user'" class="h-3 w-3" />
                                    {{ r }}
                                </span>
                                <span v-if="u.roles.length === 0" class="text-xs text-gray-400 dark:text-gray-500">无角色</span>
                            </div>
                        </td>
                        <td class="td">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    :class="u.status
                                        ? 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300'
                                        : 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300'"
                                    class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2.5 py-0.5 text-xs"
                                >
                                    <Icon :name="u.status ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'" class="h-3.5 w-3.5" />
                                    {{ u.status ? '启用' : '停用' }}
                                </span>
                                <span
                                    v-if="u.must_change_password"
                                    class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2 py-0.5 text-[11px] bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300"
                                    title="首次登录需修改密码"
                                >
                                    待改密
                                </span>
                            </div>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">
                            <template v-if="u.last_login_at">
                                {{ u.last_login_at }}
                                <span class="block text-xs text-gray-400 dark:text-gray-500">{{ u.last_login_ip || '—' }}</span>
                            </template>
                            <span v-else class="text-gray-400 dark:text-gray-500">从未登录</span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ u.created_at }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <a
                                    :href="`${userBase}/${u.id}/edit`"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="编辑"
                                >
                                    <Icon name="heroicon-o-pencil-square" class="h-4 w-4" />
                                </a>

                                <button
                                    v-if="!u.is_self"
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                                    :title="u.status ? '停用（无法登录）' : '启用'"
                                    @click="toggleStatus(u)"
                                >
                                    <Icon :name="u.status ? 'heroicon-o-pause' : 'heroicon-o-play'" class="h-4 w-4" />
                                </button>

                                <button
                                    v-if="canDestroy && !u.is_self"
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="destroyUser(u)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="users.length === 0">
                        <td colspan="9" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-user" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">没有找到用户</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
