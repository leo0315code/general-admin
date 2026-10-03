<script setup>
// 字典类型创建/编辑表单 —— 表单页 Vue 化推广
// 原生 POST 提交 + 服务端错误回显 + v-model
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PUT
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ name: '', type: '', description: '', status: true }) },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '/console/dict-types' },
    destroyUrl: { type: String, default: '' }, // edit 模式删除
    // 新建时可同页批量添加字典项（校验失败回填用）
    initialItems: { type: Array, default: () => [] },
});

const name = ref(props.old.name ?? '');
const type = ref(props.old.type ?? '');
const description = ref(props.old.description ?? '');
const status = ref(props.old.status === false ? false : true);

// 字典项行：{ label, value, sort }，字段名 items[i][xxx] 由服务端以数组方式接收
const itemRows = ref(
    props.initialItems.map((row) => ({
        label: row.label ?? '',
        value: row.value ?? '',
        sort: row.sort ?? 0,
    }))
);

function addItemRow() {
    itemRows.value.push({ label: '', value: '', sort: itemRows.value.length });
}

function removeItemRow(index) {
    itemRows.value.splice(index, 1);
}

function fieldError(field) {
    return props.errors[field] || [];
}

function itemError(index, field) {
    return props.errors[`items.${index}.${field}`] || [];
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
                title: `确定要删除类型「${name.value}」及其全部字典项吗？`,
                message: '该类型下的所有字典项将一并删除，此操作不可恢复。',
            },
        })
    );
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">

        <!-- 类型名称 -->
        <div>
            <label class="label" for="name">类型名称 <span class="text-danger-500">*</span></label>
            <input
                id="name"
                name="name"
                v-model="name"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('name').length }"
                placeholder="如：订单状态"
                required
                autofocus
            >
            <p v-for="e in fieldError('name')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 类型标识 -->
        <div>
            <label class="label" for="type">类型标识 <span class="text-danger-500">*</span></label>
            <input
                id="type"
                name="type"
                v-model="type"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('type').length }"
                placeholder="如：order_status（小写英文，用于代码）"
                required
            >
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">小写英文标识，如 order_status / pay_method。</p>
            <p v-for="e in fieldError('type')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
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
                placeholder="用途说明（选填）"
            ></textarea>
            <p v-for="e in fieldError('description')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 状态 -->
        <div>
            <label class="label">状态</label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input
                    type="checkbox"
                    name="status"
                    value="1"
                    v-model="status"
                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500 dark:bg-gray-700"
                >
                <span class="text-sm text-gray-700 dark:text-gray-200">启用</span>
            </label>
        </div>

        <!-- 字典项：新建类型时可选批量添加，保存后仍可在编辑页继续维护 -->
        <div v-if="mode === 'create'" class="pt-2 border-t border-gray-100 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">字典项（选填）</h3>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">创建类型的同时批量添加字典项，之后可在编辑页继续维护</p>
                </div>

                <button type="button" class="btn-secondary" @click="addItemRow">
                    <Icon name="heroicon-o-plus" class="h-4 w-4" />
                    添加一项
                </button>
            </div>

            <p v-for="e in fieldError('items')" :key="e" class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>

            <div v-if="itemRows.length" class="mt-3 space-y-3">
                <div
                    v-for="(row, index) in itemRows"
                    :key="index"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 p-3"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <label class="label" :for="`item-label-${index}`">名称</label>
                            <input
                                :id="`item-label-${index}`"
                                :name="`items[${index}][label]`"
                                v-model="row.label"
                                type="text"
                                class="input"
                                placeholder="如：待付款"
                            >
                            <p v-for="e in itemError(index, 'label')" :key="e" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                        </div>

                        <div class="flex-1 min-w-0">
                            <label class="label" :for="`item-value-${index}`">值</label>
                            <input
                                :id="`item-value-${index}`"
                                :name="`items[${index}][value]`"
                                v-model="row.value"
                                type="text"
                                class="input"
                                placeholder="如：pending"
                            >
                            <p v-for="e in itemError(index, 'value')" :key="e" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                        </div>

                        <div class="w-24 shrink-0">
                            <label class="label" :for="`item-sort-${index}`">排序</label>
                            <input
                                :id="`item-sort-${index}`"
                                :name="`items[${index}][sort]`"
                                v-model.number="row.sort"
                                type="number"
                                class="input"
                                min="0"
                                max="9999"
                            >
                        </div>

                        <button
                            type="button"
                            class="mt-[26px] inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                            title="移除该项"
                            @click="removeItemRow(index)"
                        >
                            <Icon name="heroicon-o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
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
                    {{ mode === 'create' ? '创建类型' : '保存修改' }}
                </span>
            </button>

            <button
                v-if="mode === 'edit' && destroyUrl"
                type="button"
                class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                @click="confirmDestroy"
            >
                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                删除类型
            </button>

            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
