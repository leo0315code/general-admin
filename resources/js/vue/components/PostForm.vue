<script setup>
// 文章创建/编辑表单 —— 表单页 Vue 化推广
// 原生 POST 提交 + 服务端错误回显 + v-model
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PUT
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ title: '', content: '', status: 'draft', published_at: '' }) },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '/console/posts' },
    destroyUrl: { type: String, default: '' }, // edit 模式删除
});

const title = ref(props.old.title ?? '');
const content = ref(props.old.content ?? '');
const status = ref(props.old.status ?? 'draft');
const publishedAt = ref(props.old.published_at ?? '');

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
                title: `确定要删除文章「${title.value}」吗？`,
                message: '删除后将进入回收站（软删除），可在回收站中还原。',
            },
        })
    );
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">

        <!-- 标题 -->
        <div>
            <label class="label" for="title">标题 <span class="text-danger-500">*</span></label>
            <input
                id="title"
                name="title"
                v-model="title"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('title').length }"
                placeholder="请输入文章标题"
                required
                autofocus
            >
            <p v-for="e in fieldError('title')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 内容 -->
        <div>
            <label class="label" for="content">内容 <span class="text-danger-500">*</span></label>
            <textarea
                id="content"
                name="content"
                v-model="content"
                rows="10"
                class="input"
                :class="{ 'input-error': fieldError('content').length }"
                placeholder="请输入文章内容…"
                required
            ></textarea>
            <p v-for="e in fieldError('content')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- 状态 -->
            <div>
                <label class="label" for="status">状态 <span class="text-danger-500">*</span></label>
                <select
                    id="status"
                    name="status"
                    v-model="status"
                    class="input"
                    :class="{ 'input-error': fieldError('status').length }"
                >
                    <option value="draft">草稿</option>
                    <option value="published">已发布</option>
                </select>
                <p v-for="e in fieldError('status')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>

            <!-- 发布时间 -->
            <div>
                <label class="label" for="published_at">发布时间</label>
                <input
                    id="published_at"
                    name="published_at"
                    v-model="publishedAt"
                    type="datetime-local"
                    class="input"
                    :class="{ 'input-error': fieldError('published_at').length }"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">选填；发布时留空将自动使用当前时间。</p>
                <p v-for="e in fieldError('published_at')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
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
                    {{ mode === 'create' ? '创建文章' : '保存修改' }}
                </span>
            </button>

            <button
                v-if="mode === 'edit' && destroyUrl"
                type="button"
                class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                @click="confirmDestroy"
            >
                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                删除文章
            </button>

            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
