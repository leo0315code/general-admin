<script setup>
// 发送历史 —— 谁发的、发给谁范围、多少人、是否撤回
// 撤回复用 AppShell 全局 ConfirmModal（app:confirm 事件 + 隐藏表单提交），与其它列表页一致
const props = defineProps({
    broadcasts: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

/** 删除同理：用 DOM API 组装隐藏表单，不做字符串拼接 */
function confirmRevoke(item) {
    const token = document.querySelector('meta[name=csrf-token]')?.content || '';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = item.revoke_url;

    const add = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    };

    add('_token', token);
    add('_method', 'DELETE');
    document.body.appendChild(form);

    window.dispatchEvent(
        new CustomEvent('app:confirm', {
            detail: {
                form,
                title: '确定撤回这条消息吗？',
                message: `「${item.title}」将从 ${item.recipients_count} 位用户的收件箱中移除，无法恢复。`,
                variant: 'danger',
            },
        })
    );
}
</script>

<template>
    <div>
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">发送历史</h3>
        </div>

        <div v-if="broadcasts.length === 0" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
            还没有发送过消息。
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">消息</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">范围</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">接收</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">发送人 / 时间</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-for="item in broadcasts" :key="item.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                        <td class="px-5 py-3 align-top">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ item.title }}</p>
                            <p v-if="item.content" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ item.content }}</p>
                            <span
                                v-if="item.revoked_at"
                                class="mt-1 inline-flex rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[11px] text-gray-500 dark:text-gray-400"
                            >已于 {{ item.revoked_at }} 撤回</span>
                        </td>
                        <td class="px-5 py-3 align-top text-sm text-gray-600 dark:text-gray-300">{{ item.scope_label }}</td>
                        <td class="px-5 py-3 align-top text-sm text-gray-600 dark:text-gray-300">{{ item.recipients_count }} 人</td>
                        <td class="px-5 py-3 align-top text-xs text-gray-500 dark:text-gray-400">
                            {{ item.sender }}
                            <span class="block">{{ item.created_at }}</span>
                        </td>
                        <td class="px-5 py-3 align-top text-right">
                            <button
                                v-if="can.revoke && !item.revoked_at"
                                type="button"
                                class="text-sm text-danger-600 hover:text-danger-700 dark:text-danger-400"
                                @click="confirmRevoke(item)"
                            >
                                撤回
                            </button>
                            <span v-else-if="item.revoked_at" class="text-xs text-gray-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
