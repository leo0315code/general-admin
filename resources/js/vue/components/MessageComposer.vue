<script setup>
// 主动发送消息 —— 范围选择（指定用户 / 按角色 / 全员）+ 内容 + 提交
//
// 提交走 JSON 而不是原生表单：选中的接收人是动态数组，走表单要拼一堆 hidden input；
// JSON 也能直接拿到 422 的字段级错误，逐项回显而不整页刷新。
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    action: { type: String, default: '' },
    indexUrl: { type: String, default: '/console/messages' },
    searchUrl: { type: String, default: '' },
    csrf: { type: String, default: '' },
    roles: { type: Array, default: () => [] },
});

const scopes = [
    { value: 'users', label: '指定用户', hint: '搜索勾选，最多 500 人' },
    { value: 'role', label: '按角色', hint: '该角色下全部启用用户' },
    { value: 'all', label: '全员', hint: '所有启用状态用户' },
];

const scope = ref('users');
const selected = ref([]); // [{ id, name, email }]
const query = ref('');
const results = ref([]);
const searching = ref(false);
const role = ref(props.roles[0] ?? '');
const title = ref('');
const content = ref('');
const link = ref('');
const submitting = ref(false);
const errors = ref({});

let debounce = null;

const canSubmit = computed(() => {
    if (!title.value.trim() || submitting.value) return false;

    if (scope.value === 'users') return selected.value.length > 0;

    return scope.value !== 'role' || !!role.value;
});

const recipientHint = computed(() => {
    if (scope.value === 'users') return `已选 ${selected.value.length} 人`;
    if (scope.value === 'role') return `角色「${role.value || '未选择'}」下的启用用户`;

    return '所有启用状态的用户';
});

/** 输入防抖 300ms 再请求，避免每敲一个字打一次接口 */
watch(query, (value) => {
    clearTimeout(debounce);

    if (value.trim() === '') {
        results.value = [];

        return;
    }

    searching.value = true;
    debounce = setTimeout(() => search(value), 300);
});

async function search(keyword) {
    try {
        const res = await fetch(`${props.searchUrl}?q=${encodeURIComponent(keyword)}`, {
            headers: { Accept: 'application/json' },
        });

        if (!res.ok) return;

        const data = await res.json();
        results.value = (data.data ?? []).filter((u) => !selected.value.some((s) => s.id === u.id));
    } catch {
        /* 静默：搜索失败不影响手动继续输入 */
    } finally {
        searching.value = false;
    }
}

function pick(user) {
    if (!selected.value.some((u) => u.id === user.id)) {
        selected.value.push({ id: user.id, name: user.name, email: user.email });
    }

    query.value = '';
    results.value = [];
}

function removeUser(id) {
    selected.value = selected.value.filter((u) => u.id !== id);
}

function toastError(message) {
    window.__ui?.toast?.error(message);
}

async function submit() {
    errors.value = {};

    if (!canSubmit.value) {
        toastError('请填写标题并选择接收人。');

        return;
    }

    submitting.value = true;

    const payload = {
        scope: scope.value,
        title: title.value.trim(),
        content: content.value.trim() || null,
        link: link.value.trim() || null,
    };

    if (scope.value === 'users') payload.user_ids = selected.value.map((u) => u.id);
    if (scope.value === 'role') payload.role = role.value;

    try {
        const res = await fetch(props.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify(payload),
        });

        if (res.status === 419) {
            toastError('登录状态已过期，请刷新页面后重试。');

            return;
        }

        const data = await res.json().catch(() => ({}));

        if (res.status === 422) {
            errors.value = data.errors ?? {};

            toastError(data.message || '请检查填写内容。');

            return;
        }

        if (!res.ok) {
            toastError(data.message || `发送失败（HTTP ${res.status}）。`);

            return;
        }

        window.__ui?.toast?.success(data.message || '消息已发送。');
        window.location.href = props.indexUrl;
    } catch {
        toastError('网络异常，消息未发送，请重试。');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="px-5 py-5 space-y-5">
        <!-- 发送范围 -->
        <div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">发送范围</p>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <label
                    v-for="item in scopes"
                    :key="item.value"
                    class="cursor-pointer rounded-xl border px-4 py-3 transition"
                    :class="scope === item.value
                        ? 'border-primary-500 bg-primary-500/5'
                        : 'border-gray-200 dark:border-gray-700 hover:border-primary-500/50'"
                >
                    <input v-model="scope" type="radio" :value="item.value" class="sr-only">
                    <span class="flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-gray-100">
                        <!-- 选中打勾（已登记图标），未选中画一个空圈（CSS，省一个图标位） -->
                        <Icon
                            v-if="scope === item.value"
                            name="heroicon-o-check-circle"
                            class="h-4 w-4 text-primary-500"
                        />
                        <span v-else class="h-4 w-4 rounded-full border-2 border-gray-300 dark:border-gray-600" />
                        {{ item.label }}
                    </span>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ item.hint }}</span>
                </label>
            </div>
        </div>

        <!-- 按范围展开的接收人选择 -->
        <div v-if="scope === 'users'" class="space-y-3">
            <div class="relative">
                <Icon name="heroicon-o-magnifying-glass" class="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="query"
                    type="search"
                    class="input pl-9"
                    placeholder="搜索用户姓名或邮箱…"
                    aria-label="搜索接收用户"
                >
            </div>

            <ul v-if="results.length" class="rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700">
                <li v-for="user in results" :key="user.id">
                    <button
                        type="button"
                        class="w-full flex items-center gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                        @click="pick(user)"
                    >
                        <Icon name="heroicon-o-user-plus" class="h-4 w-4 text-gray-400" />
                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ user.name }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</span>
                    </button>
                </li>
            </ul>
            <p v-else-if="searching" class="text-xs text-gray-500 dark:text-gray-400">搜索中…</p>

            <!-- 已选接收人 -->
            <div v-if="selected.length" class="flex flex-wrap gap-2">
                <span
                    v-for="user in selected"
                    :key="user.id"
                    class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs text-gray-700 dark:text-gray-200"
                >
                    {{ user.name }}
                    <button type="button" class="hover:text-danger-600" title="移除" @click="removeUser(user.id)">
                        <Icon name="heroicon-o-x-mark" class="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
            <p v-else class="text-xs text-gray-500 dark:text-gray-400">还没有选择接收用户。</p>

            <p v-if="errors['user_ids']" class="text-xs text-danger-600 dark:text-danger-400">{{ errors['user_ids'][0] }}</p>
        </div>

        <div v-else-if="scope === 'role'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">角色</label>
            <select v-model="role" class="input mt-1 max-w-xs" aria-label="接收角色">
                <option v-for="name in roles" :key="name" :value="name">{{ name }}</option>
            </select>
            <p v-if="errors.role" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ errors.role[0] }}</p>
        </div>

        <!-- 消息内容 -->
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">标题</label>
                <input v-model="title" type="text" maxlength="255" class="input mt-1" placeholder="例如：系统维护通知">
                <p v-if="errors.title" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ errors.title[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">正文</label>
                <textarea v-model="content" rows="4" maxlength="2000" class="input mt-1" placeholder="选填，支持多行文本" />
                <p v-if="errors.content" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ errors.content[0] }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">跳转链接（选填）</label>
                <input v-model="link" type="text" class="input mt-1" placeholder="/console/posts">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">只允许站内路径，站外地址会被自动忽略。</p>
                <p v-if="errors.link" class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ errors.link[0] }}</p>
            </div>
        </div>

        <!-- 提交 -->
        <div class="flex items-center gap-3 pt-1 border-t border-gray-200 dark:border-gray-700">
            <button type="button" class="btn-primary" :disabled="!canSubmit" @click="submit">
                <Icon name="heroicon-o-paper-airplane" class="h-4 w-4" />
                <span>{{ submitting ? '发送中…' : '发送消息' }}</span>
            </button>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ recipientHint }}</span>
            <a :href="indexUrl" class="ml-auto text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">返回历史</a>
        </div>
    </div>
</template>
