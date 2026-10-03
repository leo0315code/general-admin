<script setup>
// 附件上传区 —— 多文件队列 + 进度条 + 图片预览
//
// 设计要点：
// 1) 多文件：input 加 multiple，拖拽支持批量投放，队列里存真实 File 对象；
// 2) 进度：fetch 拿不到上传进度，改用 XMLHttpRequest（xhr.upload.onprogress）；
// 3) 失败隔离：逐个串行上传，单个失败只标红该项、可重试，不打断其它文件；
// 4) 图片预览：URL.createObjectURL 生成缩略图，组件卸载时统一释放；
// 5) 结果汇总：全部结束才刷新列表（多文件时不逐个 reload 打断流程）。
import { computed, onBeforeUnmount, ref } from 'vue';
import Icon from './Icon.vue';
import { formatBytes, isImageFile } from '../composables/useAttachmentFormat.js';

const props = defineProps({
    action: { type: String, default: '' },
    csrf: { type: String, default: '' },
    maxSizeKb: { type: Number, default: 10240 },
    hint: { type: String, default: '' },
});

const fileInput = ref(null);
const dragging = ref(false);
const uploading = ref(false);

const maxBytes = computed(() => props.maxSizeKb * 1024);

/** 上传队列项：{ key, file, status, progress, error, thumb } */
const queue = ref([]);

let keySeq = 0;
const nextKey = () => `file-${Date.now()}-${keySeq++}`;

function toast(type, message) {
    window.__ui?.toast?.[type]?.(message);
}

/** 把 FileList 追加进队列（去重：同名同大小已在队列则跳过） */
function enqueue(fileList) {
    if (!fileList) return;

    for (const file of fileList) {
        const dup = queue.value.some(
            (item) => item.file.name === file.name && item.file.size === file.size && item.status !== 'done'
        );
        if (dup) continue;

        queue.value.push({
            key: nextKey(),
            file,
            status: 'pending',
            progress: 0,
            error: '',
            thumb: isImageFile(file) ? URL.createObjectURL(file) : '',
        });
    }
}

function onPick(e) {
    enqueue(e.target.files);
    if (fileInput.value) fileInput.value.value = '';
}

function onDrop(e) {
    dragging.value = false;
    enqueue(e.dataTransfer?.files);
}

/** 移除队列项（上传中的不允许移除） */
function removeItem(item) {
    if (item.status === 'uploading') return;
    if (item.thumb) URL.revokeObjectURL(item.thumb);
    queue.value = queue.value.filter((i) => i.key !== item.key);
}

function resetQueue() {
    for (const item of queue.value) {
        if (item.thumb) URL.revokeObjectURL(item.thumb);
    }
    queue.value = [];
}

/**
 * XMLHttpRequest 上传（带进度）。fetch 不支持上传进度，必须走 XHR。
 * @returns {Promise<object>} 成功 resolve 响应 JSON；失败 reject {code, status?, data?}
 */
function uploadWithProgress(item) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', props.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                item.progress = Math.round((e.loaded / e.total) * 100);
            }
        };

        xhr.onload = () => {
            let data = {};
            try { data = JSON.parse(xhr.responseText); } catch { /* 非 JSON 响应 */ }

            // 登录态失效：Laravel 返回 419，整页刷新到登录页更可靠
            if (xhr.status === 419) {
                reject({ code: 'EXPIRED' });
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300 && data.success) {
                resolve(data);
                return;
            }

            const msg = xhr.status === 422
                ? (data.errors?.file?.[0] || data.message || '文件校验未通过。')
                : (data.message || `上传失败（HTTP ${xhr.status}）。`);
            reject({ code: 'HTTP', status: xhr.status, message: msg });
        };

        xhr.onerror = () => reject({ code: 'NETWORK', message: '网络异常，上传未成功，请重试。' });

        const body = new FormData();
        body.append('_token', props.csrf);
        body.append('file', item.file);
        xhr.send(body);
    });
}

const pendingCount = computed(() => queue.value.filter((i) => i.status === 'pending' || i.status === 'error').length);
const doneCount = computed(() => queue.value.filter((i) => i.status === 'done').length);
const canSubmit = computed(() => pendingCount.value > 0 && !uploading.value);

/** 串行上传所有 pending/error 项，失败隔离；结束后汇总提示 */
async function startUpload() {
    if (!canSubmit.value) return;

    uploading.value = true;
    const targets = queue.value.filter((i) => i.status === 'pending' || i.status === 'error');
    let ok = 0;
    let failed = 0;

    for (const item of targets) {
        item.status = 'uploading';
        item.progress = 0;
        item.error = '';

        try {
            await uploadWithProgress(item);
            item.status = 'done';
            item.progress = 100;
            ok += 1;
        } catch (err) {
            if (err?.code === 'EXPIRED') {
                toast('error', '登录状态已过期，请刷新页面后重试。');
                uploading.value = false;

                return;
            }

            item.status = 'error';
            item.error = err?.message || '上传失败，请重试。';
            failed += 1;
        }
    }

    uploading.value = false;

    if (failed === 0) {
        toast('success', ok > 1 ? `全部 ${ok} 个文件上传成功。` : '上传成功。');
        // 列表是 Blade 渲染的，刷新即可看到新附件
        window.location.reload();

        return;
    }

    if (ok > 0) {
        toast('success', `${ok} 个上传成功，${failed} 个失败。`);
        // 有成功项就刷新列表，失败项留在队列里可单独重试
        window.location.reload();
    } else {
        toast('error', `${failed} 个文件上传失败，请检查后重试。`);
    }
}

/** 单文件重试（复用同一串行上传入口） */
function retryItem(item) {
    if (item.status !== 'error' || uploading.value) return;
    item.status = 'pending';
    item.error = '';
    startUpload();
}

onBeforeUnmount(resetQueue);
</script>

<template>
    <div class="px-5 py-5 space-y-3">
        <!-- 拖拽投放区：点击或拖入（支持多选 / 多文件投放） -->
        <div
            class="relative rounded-xl border-2 border-dashed transition cursor-pointer px-6 py-8 text-center select-none"
            :class="dragging
                ? 'border-primary-500 bg-primary-500/10'
                : 'border-gray-300 dark:border-gray-600 hover:border-primary-500/60 hover:bg-primary-500/5'"
            role="button"
            tabindex="0"
            aria-label="点击或拖拽文件到此处上传（可多选）"
            @click="fileInput?.click()"
            @keydown.enter.prevent="fileInput?.click()"
            @keydown.space.prevent="fileInput?.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="fileInput"
                type="file"
                name="file"
                multiple
                class="sr-only"
                aria-hidden="true"
                tabindex="-1"
                @change="onPick"
            >

            <Icon name="heroicon-o-cloud-arrow-up" class="mx-auto h-10 w-10" :class="dragging ? 'text-primary-500' : 'text-gray-400 dark:text-gray-500'" />

            <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                <template v-if="uploading">正在上传，请稍候…</template>
                <template v-else>点击选择文件（可多选），或拖拽到此处</template>
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ hint }}</p>
        </div>

        <!-- 上传队列 -->
        <ul v-if="queue.length" class="space-y-2 max-w-xl">
            <li
                v-for="item in queue"
                :key="item.key"
                class="rounded-lg border px-3 py-2"
                :class="item.status === 'error'
                    ? 'border-danger-500/50 bg-danger-500/5'
                    : item.status === 'done'
                        ? 'border-success-500/40 bg-success-500/5'
                        : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50'"
            >
                <div class="flex items-center gap-3 text-sm">
                    <!-- 图片缩略图 / 文档图标 -->
                    <img
                        v-if="item.thumb"
                        :src="item.thumb"
                        alt=""
                        class="h-10 w-10 shrink-0 rounded object-cover bg-white dark:bg-gray-800"
                    >
                    <Icon
                        v-else
                        name="heroicon-o-document"
                        class="h-5 w-5 shrink-0 opacity-70"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-gray-800 dark:text-gray-100" :title="item.file.name">
                            {{ item.file.name }}
                        </p>
                        <p class="mt-0.5 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ formatBytes(item.file.size) }}</span>

                            <!-- 状态徽章 -->
                            <span v-if="item.status === 'done'" class="text-success-600 dark:text-success-400">· 已上传</span>
                            <span v-else-if="item.status === 'error'" class="text-danger-600 dark:text-danger-400">· 上传失败</span>
                            <span v-else-if="item.status === 'uploading'">· 上传中 {{ item.progress }}%</span>
                        </p>

                        <!-- 失败原因 -->
                        <p v-if="item.error" class="mt-0.5 text-xs text-danger-600 dark:text-danger-400">{{ item.error }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <button
                            v-if="item.status === 'error' && !uploading"
                            type="button"
                            class="rounded-lg p-1.5 text-primary-600 hover:bg-primary-500/10 dark:text-primary-400 transition"
                            title="重试"
                            @click="retryItem(item)"
                        >
                            <Icon name="heroicon-o-arrow-path" class="h-4 w-4" />
                        </button>
                        <button
                            v-if="item.status !== 'uploading'"
                            type="button"
                            class="rounded-lg p-1.5 transition hover:bg-gray-200 dark:hover:bg-gray-600"
                            title="从队列移除"
                            @click="removeItem(item)"
                        >
                            <Icon name="heroicon-o-x-mark" class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- 进度条 -->
                <div
                    v-if="item.status === 'uploading'"
                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-600"
                >
                    <div
                        class="h-full rounded-full bg-primary-500 transition-[width] duration-200"
                        :style="{ width: `${item.progress}%` }"
                    />
                </div>
            </li>
        </ul>

        <!-- 底部操作栏 -->
        <div v-if="queue.length" class="flex items-center justify-between gap-3 max-w-xl">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                共 {{ queue.length }} 个文件<template v-if="doneCount">，{{ doneCount }} 个已上传</template>
            </p>

            <button type="button" class="btn-primary" :disabled="!canSubmit" @click="startUpload">
                <Icon name="heroicon-o-arrow-up-tray" class="h-4 w-4" />
                <span>{{ uploading ? `上传中 ${doneCount}/${queue.length}…` : (pendingCount ? `上传全部（${pendingCount}）` : '已全部上传') }}</span>
            </button>
        </div>
    </div>
</template>
