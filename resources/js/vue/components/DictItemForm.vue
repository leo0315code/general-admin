<script setup>
// 字典项创建/编辑表单 —— 表单页 Vue 化推广
// 原生 POST 提交 + 服务端错误回显 + v-model
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PUT
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ label: '', value: '', sort: 0, status: true, remark: '' }) },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '/console/dict-items' },
    destroyUrl: { type: String, default: '' }, // edit 模式删除
});

const label = ref(props.old.label ?? '');
const value = ref(props.old.value ?? '');
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
                title: `确定要删除字典项「${label.value}」吗？`,
                message: '删除后该字典项将无法恢复。',
            },
        })
    );
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">

        <!-- 字典项名称 -->
        <div>
            <label class="label" for="label">字典项名称 <span class="text-danger-500">*</span></label>
            <input
                id="label"
                name="label"
                v-model="label"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('label').length }"
                placeholder="如：待付款"
                required
                autofocus
            >
            <p v-for="e in fieldError('label')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 字典项值 -->
        <div>
            <label class="label" for="value">字典项值 <span class="text-danger-500">*</span></label>
            <input
                id="value"
                name="value"
                v-model="value"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('value').length }"
                placeholder="如：pending（同一类型下唯一）"
                required
            >
            <p v-for="e in fieldError('value')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- 排序 -->
            <div>
                <label class="label" for="sort">排序</label>
                <input
                    id="sort"
                    name="sort"
                    v-model.number="sort"
                    type="number"
                    class="input"
                    :class="{ 'input-error': fieldError('sort').length }"
                    min="0"
                    max="9999"
                >
                <p v-for="e in fieldError('sort')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>

            <!-- 状态 -->
            <div>
                <label class="label">状态</label>
                <label class="inline-flex items-center gap-2 cursor-pointer mt-2">
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
                placeholder="选填"
            >
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
                    {{ mode === 'create' ? '创建字典项' : '保存修改' }}
                </span>
            </button>

            <button
                v-if="mode === 'edit' && destroyUrl"
                type="button"
                class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                @click="confirmDestroy"
            >
                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                删除字典项
            </button>

            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
