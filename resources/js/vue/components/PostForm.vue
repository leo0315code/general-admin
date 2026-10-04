<script setup>
// 文章创建/编辑表单 —— 表单页 Vue 化推广
// 原生 POST 提交 + 服务端错误回显 + v-model
// 封面：内联上传到附件基座（落 attachments 表），提交时带 hidden cover_attachment_id
import { ref } from 'vue';
import { useConfirmAction } from '../composables/useConfirmAction.js';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PUT
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ title: '', content: '', status: 'draft', published_at: '', cover_attachment_id: null, cover_preview_url: '' }) },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '/console/posts' },
    destroyUrl: { type: String, default: '' }, // edit 模式删除
    /** 封面上传接口（posts.cover-upload） */
    coverUploadUrl: { type: String, default: '' },
    /** 单文件上限（MB），仅展示提示用 */
    maxSizeMb: { type: Number, default: 10 },
});

const title = ref(props.old.title ?? '');
const content = ref(props.old.content ?? '');
const status = ref(props.old.status ?? 'draft');
const publishedAt = ref(props.old.published_at ?? '');
const coverId = ref(props.old.cover_attachment_id ?? null);
const coverUrl = ref(props.old.cover_preview_url ?? '');
const coverInput = ref(null);
const coverUploading = ref(false);

const { confirmAction } = useConfirmAction();

function fieldError(field) {
    return props.errors[field] || [];
}

/** 内联上传封面：复用附件基座，成功后回填 id + 预览 URL */
async function uploadCover(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!props.coverUploadUrl) return;

    coverUploading.value = true;
    const body = new FormData();
    body.append('_token', props.csrf);
    body.append('file', file);

    try {
        const res = await fetch(props.coverUploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });

        if (res.status === 419) {
            window.__ui?.toast?.error('登录状态已过期，请刷新页面后重试。');
            return;
        }

        const data = await res.json().catch(() => ({}));

        if (res.ok && data.success) {
            coverId.value = data.id;
            coverUrl.value = data.preview_url;
            window.__ui?.toast?.success('封面上传成功。');
            return;
        }

        window.__ui?.toast?.error(data.message || `封面上传失败（HTTP ${res.status}）。`);
    } catch {
        window.__ui?.toast?.error('网络异常，封面上传失败，请重试。');
    } finally {
        coverUploading.value = false;
        if (coverInput.value) coverInput.value.value = '';
    }
}

function removeCover() {
    coverId.value = null;
    coverUrl.value = '';
}

function confirmDestroy() {
    confirmAction({
        action: props.destroyUrl,
        method: 'DELETE',
        title: `确定要删除文章「${title.value}」吗？`,
        message: '删除后将进入回收站（软删除），可在回收站中还原。',
    });
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">
        <input type="hidden" name="cover_attachment_id" :value="coverId || ''">

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

        <!-- 封面（附件基座业务接入：私有盘 + 鉴权预览） -->
        <div v-if="coverUploadUrl">
            <label class="label">封面</label>
            <div class="flex items-center gap-4">
                <div
                    class="flex h-28 w-40 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50"
                >
                    <img
                        v-if="coverUrl"
                        :src="coverUrl"
                        alt="封面预览"
                        class="h-full w-full object-cover"
                    >
                    <Icon
                        v-else
                        name="heroicon-o-photo"
                        class="h-8 w-8 text-gray-300 dark:text-gray-600"
                    />
                </div>

                <div class="space-y-2">
                    <input ref="coverInput" type="file" accept="image/*" class="sr-only" @change="uploadCover">
                    <div class="flex items-center gap-2">
                        <button
                            v-if="!coverUrl"
                            type="button"
                            class="btn-secondary !px-3 !py-1.5 text-xs"
                            :disabled="coverUploading"
                            @click="coverInput?.click()"
                        >
                            <Icon :name="coverUploading ? 'heroicon-o-arrow-path' : 'heroicon-o-cloud-arrow-up'" class="h-3.5 w-3.5" :class="{ 'animate-spin': coverUploading }" />
                            {{ coverUploading ? '上传中…' : '上传封面' }}
                        </button>
                        <button
                            v-if="coverUrl"
                            type="button"
                            class="btn-danger-ghost !px-3 !py-1.5 text-xs"
                            @click="removeCover"
                        >
                            <Icon name="heroicon-o-trash" class="h-3.5 w-3.5" />
                            移除封面
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">jpg / png / gif / webp，单文件上限 {{ maxSizeMb }} MB；不上传则无封面。</p>
                </div>
            </div>
            <p v-for="e in fieldError('cover_attachment_id')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
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
                class="btn-danger-outline"
                @click="confirmDestroy"
            >
                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                删除文章
            </button>

            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
