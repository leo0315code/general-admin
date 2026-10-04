<script setup>
// 通知中心列表 —— 未读高亮、标记已读、删除
// 写操作复用 AppShell 全局 Vue ConfirmModal（app:confirm 事件 + 隐藏表单提交）
import { useConfirmAction } from '../composables/useConfirmAction.js';
import Icon from './Icon.vue';

const props = defineProps({
    notifications: { type: Array, default: () => [] },
    filter: { type: String, default: 'all' },
    unreadCount: { type: Number, default: 0 },
    base: { type: String, default: '/console/notifications' }, // /console/notifications
});

const TYPE_LABELS = {
    'account.created': '账号创建',
    'account.password_reset': '密码重置',
    'users.imported': '用户导入',
};

const { confirmAction, submitHiddenForm } = useConfirmAction();

function typeLabel(type) {
    return TYPE_LABELS[type] || '系统消息';
}

function markRead(item) {
    // 标记已读无需二次确认
    submitHiddenForm({ action: `${props.base}/${item.id}/read`, method: 'PATCH' });
}

function destroy(item) {
    confirmAction({
        action: `${props.base}/${item.id}`,
        method: 'DELETE',
        title: '确定删除这条通知吗？',
        message: item.title,
    });
}
</script>

<template>
    <div>
        <div class="card-header flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm text-gray-600 dark:text-gray-300">
                共 {{ notifications.length }} 条
                <span v-if="unreadCount > 0" class="text-primary-600 dark:text-primary-400 font-medium">
                    · {{ unreadCount }} 条未读
                </span>
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500">
                {{ filter === 'unread' ? '当前筛选：仅未读' : '当前筛选：全部' }}
            </span>
        </div>

        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            <li
                v-for="item in notifications"
                :key="item.id"
                class="px-5 py-4 flex gap-3 transition"
                :class="item.is_read ? 'opacity-75' : 'bg-primary-50/40 dark:bg-primary-500/5'"
            >
                <!-- 未读指示点 -->
                <span class="mt-1.5 shrink-0">
                    <span
                        v-if="!item.is_read"
                        class="block h-2 w-2 rounded-full bg-primary-500"
                        title="未读"
                    ></span>
                    <Icon v-else name="heroicon-o-check" class="h-4 w-4 text-gray-400" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                            :class="item.is_read
                                ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'
                                : 'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300'"
                        >{{ typeLabel(item.type) }}</span>

                        <a
                            v-if="item.link"
                            :href="item.link"
                            class="font-medium text-gray-900 dark:text-gray-100 hover:text-primary-600 dark:hover:text-primary-400 truncate"
                        >{{ item.title }}</a>
                        <span v-else class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ item.title }}</span>
                    </div>

                    <p v-if="item.content" class="mt-1 text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">
                        {{ item.content }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ item.created_at }}</p>
                </div>

                <div class="shrink-0 self-start flex items-center gap-0.5">
                    <button
                        v-if="!item.is_read"
                        type="button"
                        class="inline-flex items-center justify-center p-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition"
                        title="标记为已读"
                        @click="markRead(item)"
                    >
                        <Icon name="heroicon-o-check" class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center p-1.5 rounded-lg text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition"
                        title="删除通知"
                        @click="destroy(item)"
                    >
                        <Icon name="heroicon-o-trash" class="h-4 w-4" />
                    </button>
                </div>
            </li>

            <li v-if="notifications.length === 0" class="px-6 py-14 text-center">
                <Icon name="heroicon-o-bell" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-300">
                    {{ filter === 'unread' ? '没有未读通知' : '暂无通知' }}
                </p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                    账号创建、密码重置、用户导入等事件会在这里通知你
                </p>
            </li>
        </ul>
    </div>
</template>
