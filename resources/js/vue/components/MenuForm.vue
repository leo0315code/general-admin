<script setup>
// 菜单/权限节点创建编辑表单 —— 表单页 Vue 化
// 原生 POST 提交 + 错误回显 + v-model；父级树/类型/路由+图标 datalist 建议
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PUT
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ pid: 0, type: 'menu', title: '', permission_name: '', route: '', icon: '', sort: 0, status: true, remark: '' }) },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '/console/menus' },
    destroyUrl: { type: String, default: '' }, // edit 模式删除
    parents: { type: Array, default: () => [] }, // [{id, label}]
    typeOptions: { type: Array, default: () => [] }, // [{value, label, hint}]
    routeSuggestions: { type: Array, default: () => [] },
    iconSuggestions: { type: Array, default: () => [] },
});

const pid = ref(String(props.old.pid ?? 0));
const type = ref(props.old.type ?? 'menu');
const title = ref(props.old.title ?? '');
const permissionName = ref(props.old.permission_name ?? '');
const route = ref(props.old.route ?? '');
const icon = ref(props.old.icon ?? '');
const sort = ref(props.old.sort ?? 0);
const status = ref(props.old.status === false ? false : true);
const remark = ref(props.old.remark ?? '');

function fieldError(field) {
    return props.errors[field] || [];
}

function confirmDestroy() {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = props.destroyUrl;
    form.innerHTML = `<input type="hidden" name="_token" value="${token}"><input type="hidden" name="_method" value="DELETE">`;
    document.body.appendChild(form);
    window.dispatchEvent(
        new CustomEvent('app:confirm', {
            detail: {
                form,
                title: `确定要删除「${title.value}」吗？`,
                message: '对应权限记录将一并清理。',
            },
        })
    );
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">

        <!-- 上级节点 -->
        <div>
            <label class="label" for="pid">上级节点 <span class="text-danger-500">*</span></label>
            <select
                id="pid"
                name="pid"
                v-model="pid"
                class="input"
                :class="{ 'input-error': fieldError('pid').length }"
            >
                <option value="0">顶级节点</option>
                <option v-for="row in parents" :key="row.id" :value="row.id">{{ row.label }}</option>
            </select>
            <p v-for="e in fieldError('pid')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 节点类型 -->
        <div>
            <label class="label">节点类型 <span class="text-danger-500">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                <label
                    v-for="opt in typeOptions"
                    :key="opt.value"
                    class="flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 px-3.5 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                >
                    <input
                        type="radio"
                        name="type"
                        :value="opt.value"
                        v-model="type"
                        class="mt-0.5 rounded-full border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ opt.label }}</span>
                        <span class="block mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ opt.hint }}</span>
                    </span>
                </label>
            </div>
            <p v-for="e in fieldError('type')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- 显示名称 -->
            <div>
                <label class="label" for="title">显示名称 <span class="text-danger-500">*</span></label>
                <input
                    id="title"
                    name="title"
                    v-model="title"
                    type="text"
                    class="input"
                    :class="{ 'input-error': fieldError('title').length }"
                    placeholder="如：用户管理"
                    required
                >
                <p v-for="e in fieldError('title')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>

            <!-- 权限标识 -->
            <div>
                <label class="label" for="permission_name">权限标识</label>
                <input
                    id="permission_name"
                    name="permission_name"
                    v-model="permissionName"
                    type="text"
                    class="input font-mono"
                    :class="{ 'input-error': fieldError('permission_name').length }"
                    placeholder="如：users.create"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">留空则不参与鉴权（纯目录可留空）。保存后自动同步到权限表，角色授权时按此标识勾选。</p>
                <p v-for="e in fieldError('permission_name')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- 路由名 -->
            <div>
                <label class="label" for="route">路由名</label>
                <input
                    id="route"
                    name="route"
                    v-model="route"
                    type="text"
                    class="input font-mono"
                    :class="{ 'input-error': fieldError('route').length }"
                    list="route-suggestions"
                    placeholder="如：users.index"
                >
                <datalist id="route-suggestions">
                    <option v-for="name in routeSuggestions" :key="name" :value="name"></option>
                </datalist>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">目录与按钮节点留空；需填写真实存在的路由名，否则菜单不可点击。</p>
                <p v-for="e in fieldError('route')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>

            <!-- 图标 -->
            <div>
                <label class="label" for="icon">图标</label>
                <input
                    id="icon"
                    name="icon"
                    v-model="icon"
                    type="text"
                    class="input font-mono"
                    :class="{ 'input-error': fieldError('icon').length }"
                    list="icon-suggestions"
                    placeholder="如：heroicon-o-users"
                >
                <datalist id="icon-suggestions">
                    <option v-for="ic in iconSuggestions" :key="ic" :value="ic"></option>
                </datalist>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Heroicons 2.0 名称（heroicon-o-*），留空显示占位方块。</p>
                <p v-for="e in fieldError('icon')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- 排序 -->
            <div>
                <label class="label" for="sort">排序</label>
                <input
                    id="sort"
                    name="sort"
                    v-model.number="sort"
                    type="number"
                    min="0"
                    max="9999"
                    class="input"
                    :class="{ 'input-error': fieldError('sort').length }"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">数字越小越靠前，同级之间比较。</p>
                <p v-for="e in fieldError('sort')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>

            <!-- 状态 -->
            <div>
                <label class="label">状态</label>
                <label class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 cursor-pointer">
                    <input type="hidden" name="status" :value="status ? 1 : 0">
                    <input
                        id="status"
                        type="checkbox"
                        v-model="status"
                        class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                    >
                    <span class="text-sm text-gray-700 dark:text-gray-200">启用（停用后不出现在侧边栏）</span>
                </label>
            </div>
        </div>

        <!-- 备注 -->
        <div>
            <label class="label" for="remark">备注</label>
            <input
                id="remark"
                name="remark"
                v-model="remark"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('remark').length }"
                placeholder="权限说明（会写入权限表的描述字段，选填）"
            >
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">权限说明（会写入权限表的描述字段，选填）。</p>
            <p v-for="e in fieldError('remark')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
            <button type="submit" class="btn-primary" data-submit-button>
                <svg data-loading-spinner class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span data-loading-label class="hidden">提交中…</span>
                <span data-label class="inline-flex items-center gap-1.5">
                    <Icon :name="mode === 'create' ? 'heroicon-o-plus' : 'heroicon-o-check'" class="h-4 w-4" />
                    {{ mode === 'create' ? '创建节点' : '保存修改' }}
                </span>
            </button>

            <button
                v-if="mode === 'edit' && destroyUrl"
                type="button"
                class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                @click="confirmDestroy"
            >
                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                删除节点
            </button>

            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
