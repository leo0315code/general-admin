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
});

const name = ref(props.old.name ?? '');
const type = ref(props.old.type ?? '');
const description = ref(props.old.description ?? '');
const status = ref(props.old.status === false ? false : true);

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
