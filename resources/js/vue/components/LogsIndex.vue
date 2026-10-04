<script setup>
// 操作日志列表页 —— CRUD 样板推广（Vue 组件化，只读表格）
// 筛选栏（搜索/操作类型/日期）+ 排序表头，表格 Vue 渲染，分页 Blade 渲染
import { ref } from 'vue';
import { useSortable } from '../composables/useSortable.js';
import Icon from './Icon.vue';

const props = defineProps({
    keyword: { type: String, default: '' },
    action: { type: String, default: '' },
    date: { type: String, default: '' },
    actionOptions: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
    sort: { type: String, default: 'id' },
    sortDir: { type: String, default: 'desc' },
    currentUrl: { type: String, default: '' },
    query: { type: Object, default: () => ({}) },
    /** 详情页链接模板（用 __ID__ 占位，实际 id 前端替换） */
    detailUrl: { type: String, default: '' },
});

/** 行详情链接 */
function detailHref(log) {
    return props.detailUrl ? props.detailUrl.replace('__ID__', String(log.id)) : '#';
}

const { sortUrl, sortIcon } = useSortable(props);

const search = ref(props.keyword || '');
const filterAction = ref(props.action || '');
const filterDate = ref(props.date || '');

function buildQuery() {
    const q = new URLSearchParams();
    if (search.value.trim()) q.set('search', search.value.trim());
    if (filterAction.value) q.set('action', filterAction.value);
    if (filterDate.value) q.set('date', filterDate.value);
    return q;
}

function submitFilter() {
    window.location.href = `${props.currentUrl}${buildQuery().toString() ? '?' + buildQuery().toString() : ''}`;
}

function clearFilter() {
    window.location.href = props.currentUrl;
}

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'created_at', label: '时间', sortable: true },
    { key: null, label: '用户' },
    { key: 'action', label: '操作类型', sortable: true },
    { key: null, label: '描述' },
    { key: 'ip', label: 'IP 地址', sortable: true },
    { key: null, label: '操作', align: 'right' },
];

// 操作类型 → 徽章配色 + 图标（图标名必须已登记在 Icon.vue 的 paths 白名单里）
const ACTION_STYLES = {
    创建: { color: 'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300', icon: 'heroicon-o-plus-circle' },
    修改: { color: 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300', icon: 'heroicon-o-pencil-square' },
    删除: { color: 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300', icon: 'heroicon-o-trash' },
    导入: { color: 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300', icon: 'heroicon-o-arrow-up-tray' },
    导出: { color: 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300', icon: 'heroicon-o-arrow-down-tray' },
    登录: { color: 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300', icon: 'heroicon-o-arrow-right-on-rectangle' },
    退出: { color: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300', icon: 'heroicon-o-arrow-left-on-rectangle' },
    还原: { color: 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300', icon: 'heroicon-o-arrow-uturn-left' },
};

const FALLBACK_STYLE = {
    color: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    icon: 'heroicon-o-clipboard-document-list',
};

function actionStyle(action) {
    const name = String(action);

    // 失败类优先按危险色处理（登录失败 / 导入失败 …）
    if (name.includes('失败')) {
        return {
            color: 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
            icon: 'heroicon-o-x-circle',
        };
    }

    return ACTION_STYLES[name] ?? FALLBACK_STYLE;
}

function initial(name) {
    return name ? name.charAt(0).toUpperCase() : '?';
}
</script>

<template>
    <div>
        <!-- 筛选栏 -->
        <div class="card-header">
            <form class="flex flex-col sm:flex-row gap-3" @submit.prevent="submitFilter">
                <div class="relative flex-1 sm:max-w-xs">
                    <Icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="search"
                        id="logs-search"
                        type="search"
                        name="search"
                        placeholder="搜索用户名 / 描述 / IP…"
                        class="input pl-9"
                    >
                </div>
                <select v-model="filterAction" name="action" class="input sm:w-44">
                    <option value="">全部操作类型</option>
                    <option v-for="opt in actionOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <input v-model="filterDate" type="date" name="date" class="input sm:w-44" title="按日期筛选">
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">筛选</button>
                    <a v-if="keyword !== '' || action || date" href="#" class="btn-secondary" @click.prevent="clearFilter">清除</a>
                </div>
            </form>
        </div>

        <!-- 日志表格 -->
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
                    <tr v-for="log in logs" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td text-gray-500 dark:text-gray-400">{{ log.id }}</td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ log.created_at }}</td>
                        <td class="td">
                            <span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-200">
                                <span
                                    v-if="log.user_id"
                                    class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-[10px] font-semibold"
                                >
                                    {{ initial(log.username) }}
                                </span>
                                <Icon v-else name="heroicon-o-user-minus" class="h-4 w-4 text-gray-400" />
                                {{ log.username || '—' }}
                            </span>
                        </td>
                        <td class="td">
                            <span
                                :class="actionStyle(log.action).color"
                                class="inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap px-2.5 py-0.5 text-xs"
                            >
                                <Icon :name="actionStyle(log.action).icon" class="h-3.5 w-3.5" />
                                {{ log.action }}
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 max-w-xs truncate">{{ log.description || '—' }}</td>
                        <td class="td text-gray-500 dark:text-gray-400 font-mono text-xs">{{ log.ip || '—' }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <a :href="detailHref(log)" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
                                详情
                            </a>
                        </td>
                    </tr>

                    <tr v-if="logs.length === 0">
                        <td colspan="7" class="px-6 py-12 text-center">
                            <Icon name="heroicon-o-clipboard-document-list" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                            <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">暂无操作日志</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
