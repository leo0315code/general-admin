<script setup>
// 角色创建/编辑表单 —— 表单页 Vue 化
// 权限树按菜单扁平渲染（缩进），支持全选/全选本组；admin 角色标识只读、无删除
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PATCH
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ name: '', description: '', permissions: [] }) },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '/console/roles' },
    destroyUrl: { type: String, default: '' }, // edit 非 admin 删除
    nameReadonly: { type: Boolean, default: false },
    canDestroy: { type: Boolean, default: false },
    permissionRows: { type: Array, default: () => [] }, // 菜单扁平树
});

const name = ref(props.old.name ?? '');
const description = ref(props.old.description ?? '');
const selected = ref((props.old.permissions || []).map(String));

function isChecked(pid) {
    return pid !== null && pid !== undefined && selected.value.includes(String(pid));
}

function togglePermission(pid) {
    if (pid === null || pid === undefined) return;
    const s = String(pid);
    const i = selected.value.indexOf(s);
    if (i >= 0) selected.value.splice(i, 1);
    else selected.value.push(s);
}

// 全选本组：对组内权限 ids 批量勾选/取消（勾选状态 = 组内全部已勾选）
function toggleGroup(row) {
    const ids = (row.group_ids || []).map(String);
    const allChecked = ids.length > 0 && ids.every((id) => selected.value.includes(id));
    if (allChecked) {
        selected.value = selected.value.filter((s) => !ids.includes(s));
    } else {
        ids.forEach((id) => {
            if (!selected.value.includes(id)) selected.value.push(id);
        });
    }
}

function groupChecked(row) {
    const ids = (row.group_ids || []).map(String);
    return ids.length > 0 && ids.every((id) => selected.value.includes(id));
}

// 全选 / 取消全选
const allChecked = ref(false);
function toggleAll(source) {
    const checkable = props.permissionRows
        .map((r) => r.permission_id)
        .filter((pid) => pid !== null && pid !== undefined)
        .map(String);
    if (source) {
        checkable.forEach((id) => {
            if (!selected.value.includes(id)) selected.value.push(id);
        });
    } else {
        selected.value = selected.value.filter((s) => !checkable.includes(s));
    }
}

function typeBadge(type) {
    if (type === 'dir') return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300';
    if (type === 'button') return 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300';
    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300';
}

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
                title: `确定要删除角色「${name.value}」吗？`,
                message: '删除后该角色及其权限分配将一并移除。',
            },
        })
    );
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">

        <!-- 角色标识 -->
        <div>
            <label class="label" for="name">角色标识 <span class="text-danger-500">*</span></label>
            <input
                id="name"
                name="name"
                v-model="name"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('name').length }"
                placeholder="如：operator（小写英文，用于代码判断）"
                required
                autofocus
                :readonly="nameReadonly"
            >
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">小写英文标识，用于代码中的角色判断（如 admin / editor）。</p>
            <p v-if="nameReadonly" class="text-xs text-amber-600 dark:text-amber-400 mt-1.5">内置 admin 角色的标识不允许修改。</p>
            <p v-for="e in fieldError('name')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 描述 -->
        <div>
            <label class="label" for="description">描述</label>
            <textarea
                id="description"
                name="description"
                v-model="description"
                rows="2"
                class="input"
                :class="{ 'input-error': fieldError('description').length }"
                placeholder="角色职责说明（选填）"
            ></textarea>
            <p v-for="e in fieldError('description')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 权限分配 -->
        <div>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <label class="label mb-0">权限分配</label>
                <label class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 cursor-pointer">
                    <input
                        type="checkbox"
                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        @change="toggleAll($event.target.checked)"
                    >
                    全选 / 取消全选
                </label>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">按菜单树勾选该角色可访问的菜单与按钮；admin 角色自动拥有全部权限。菜单与权限同源，维护入口在「菜单管理」。</p>

            <div class="space-y-3">
                <!-- 权限树（扁平渲染，深度缩进） -->
                <div
                    v-for="row in permissionRows"
                    :key="row.id"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 px-3.5 py-2.5"
                    :class="row.depth > 0 ? 'bg-gray-50/70 dark:bg-gray-900/30' : 'bg-white dark:bg-gray-800'"
                    :style="{ marginLeft: row.depth * 18 + 'px' }"
                >
                    <div class="flex items-center gap-2.5">
                        <input
                            v-if="row.permission_id !== null && row.permission_id !== undefined"
                            type="checkbox"
                            name="permissions[]"
                            :value="row.permission_id"
                            :checked="isChecked(row.permission_id)"
                            @change="togglePermission(row.permission_id)"
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        >
                        <span
                            v-else
                            class="inline-block h-4 w-4 rounded border border-dashed border-gray-300 dark:border-gray-600"
                            title="该节点未配置权限标识，不参与鉴权"
                        ></span>

                        <span class="text-sm" :class="row.depth === 0 ? 'font-semibold' : 'text-gray-700 dark:text-gray-200'">
                            {{ row.title }}
                        </span>
                        <span :class="typeBadge(row.type)" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium">
                            {{ row.type_label }}
                        </span>
                        <code v-if="row.permission_name" class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ row.permission_name }}</code>
                        <span v-if="!row.status" class="text-[11px] text-gray-400 dark:text-gray-500">（已停用）</span>

                        <label
                            v-if="row.group_ids && row.group_ids.length"
                            class="ml-auto inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                :checked="groupChecked(row)"
                                @change="toggleGroup(row)"
                            >
                            全选本组
                        </label>
                    </div>
                </div>
            </div>

            <p v-for="e in fieldError('permissions')" :key="e" class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
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
                    {{ mode === 'create' ? '创建角色' : '保存修改' }}
                </span>
            </button>

            <button
                v-if="mode === 'edit' && canDestroy"
                type="button"
                class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                @click="confirmDestroy"
            >
                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                删除角色
            </button>

            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
