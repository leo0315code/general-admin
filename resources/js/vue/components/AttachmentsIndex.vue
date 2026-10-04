<script setup>
// 附件列表 —— 搜索（Blade 刷新）/ 下载 / 删除
// 删除复用 AppShell 全局 Vue ConfirmModal（app:confirm 事件 + 隐藏表单提交）
import { useConfirmAction } from '../composables/useConfirmAction.js';
import EmptyState from './EmptyState.vue';
import Icon from './Icon.vue';

const props = defineProps({
    attachments: { type: Array, default: () => [] },
    keyword: { type: String, default: '' },
    can: { type: Object, default: () => ({}) },
    base: { type: String, default: '/console/attachments' },
});

const { confirmAction } = useConfirmAction();

function confirmDestroy(item) {
    confirmAction({
        action: `${props.base}/${item.id}`,
        method: 'DELETE',
        title: '确定删除这个附件吗？',
        message: `「${item.name}」删除后文件将一并移除，无法恢复。`,
    });
}
</script>

<template>
    <div>
        <!-- 搜索栏（GET 整页刷新，与列表页样板一致） -->
        <div class="card-header">
            <form method="GET" :action="base" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <Icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                        type="search"
                        name="search"
                        :value="keyword"
                        placeholder="搜索文件名…"
                        class="input pl-9"
                        aria-label="搜索附件"
                    >
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">搜索</button>
                    <a v-if="keyword" :href="base" class="btn-secondary">清除</a>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="th">文件名</th>
                        <th class="th">类型</th>
                        <th class="th">大小</th>
                        <th class="th">上传者</th>
                        <th class="th">上传时间</th>
                        <th class="th text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="item in attachments" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <td class="td max-w-xs truncate">
                            <span class="inline-flex items-center gap-2">
                                <Icon
                                    :name="item.is_image ? 'heroicon-o-photo' : 'heroicon-o-document-text'"
                                    class="h-4 w-4 shrink-0 text-gray-400"
                                />
                                <span class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ item.name }}</span>
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-xs font-medium uppercase">
                                {{ item.extension }}
                            </span>
                        </td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ item.size_human }}</td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ item.uploader || '—' }}</td>
                        <td class="td text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ item.created_at }}</td>
                        <td class="td text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-0.5">
                                <a
                                    :href="item.download_url"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                                    title="下载"
                                >
                                    <Icon name="heroicon-o-arrow-down-tray" class="h-4 w-4" />
                                </a>
                                <button
                                    v-if="can.destroy"
                                    type="button"
                                    class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                                    title="删除"
                                    @click="confirmDestroy(item)"
                                >
                                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="attachments.length === 0">
                        <EmptyState :colspan="6" icon="heroicon-o-paper-clip">
                            {{ keyword ? '没有匹配的附件' : '还没有上传任何附件' }}
                        </EmptyState>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
