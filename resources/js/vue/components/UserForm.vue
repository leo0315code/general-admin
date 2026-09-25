<script setup>
// 用户创建/编辑表单 —— 表单页 Vue 化样板
// 提交走原生 form POST（CSRF / _method / 服务端校验回显不变），
// v-model 双向绑定保证提交瞬间不会丢失输入。
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    action: { type: String, default: '' },
    method: { type: String, default: 'POST' }, // POST | PATCH
    csrf: { type: String, default: '' },
    old: { type: Object, default: () => ({ name: '', email: '', roles: [] }) },
    errors: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    userRoleIds: { type: Array, default: () => [] },
    canUpdate: { type: Boolean, default: true },
    indexUrl: { type: String, default: '/console/users' },
});

const name = ref(props.old.name ?? '');
const email = ref(props.old.email ?? '');
const password = ref('');
const passwordConfirmation = ref('');
const selectedRoles = ref(
    Array.isArray(props.old.roles) && props.old.roles.length > 0
        ? props.old.roles.map(String)
        : props.userRoleIds.map(String)
);

function toggleRole(id) {
    const s = String(id);
    const i = selectedRoles.value.indexOf(s);
    if (i >= 0) selectedRoles.value.splice(i, 1);
    else selectedRoles.value.push(s);
}

function fieldError(field) {
    return props.errors[field] || [];
}
</script>

<template>
    <form :action="action" :method="method === 'GET' ? 'GET' : 'POST'" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST' && method !== 'GET'" type="hidden" name="_method" :value="method">

        <!-- 姓名 -->
        <div>
            <label class="label" for="name">姓名 <span class="text-danger-500">*</span></label>
            <input
                id="name"
                name="name"
                v-model="name"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError('name').length }"
                placeholder="请输入姓名"
                required
                autofocus
                :disabled="!canUpdate"
            >
            <p v-for="e in fieldError('name')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 邮箱 -->
        <div>
            <label class="label" for="email">邮箱 <span class="text-xs font-normal text-gray-400 dark:text-gray-500">（选填）</span></label>
            <input
                id="email"
                name="email"
                v-model="email"
                type="email"
                class="input"
                :class="{ 'input-error': fieldError('email').length }"
                placeholder="name@example.com（选填，留空表示无邮箱）"
                :disabled="!canUpdate"
            >
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">选填。留空则该账号无邮箱（无法接收邮件通知 / 找回密码），登录仍可用用户名。</p>
            <p v-for="e in fieldError('email')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <!-- 密码 -->
        <template v-if="mode === 'create'">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="password">密码 <span class="text-danger-500">*</span></label>
                    <input
                        id="password"
                        name="password"
                        v-model="password"
                        type="password"
                        class="input"
                        :class="{ 'input-error': fieldError('password').length }"
                        required
                        autocomplete="new-password"
                        :disabled="!canUpdate"
                    >
                    <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">至少 10 位，需同时包含字母与数字</p>
                    <p v-for="e in fieldError('password')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                </div>
                <div>
                    <label class="label" for="password_confirmation">确认密码 <span class="text-danger-500">*</span></label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        v-model="passwordConfirmation"
                        type="password"
                        class="input"
                        :class="{ 'input-error': fieldError('password_confirmation').length }"
                        required
                        autocomplete="new-password"
                        :disabled="!canUpdate"
                    >
                    <p v-for="e in fieldError('password_confirmation')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                </div>
            </div>
        </template>

        <!-- 角色分配（多选） -->
        <div>
            <label class="label" for="roles">角色分配</label>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">可多选；用户的权限为所分配角色权限的并集。</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <label
                    v-for="role in roles"
                    :key="role.id"
                    class="flex items-center gap-2.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                >
                    <input
                        type="checkbox"
                        name="roles[]"
                        :value="role.id"
                        :checked="selectedRoles.includes(String(role.id))"
                        @change="toggleRole(role.id)"
                        class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                        :disabled="!canUpdate"
                    >
                    <span class="text-sm text-gray-700 dark:text-gray-200">{{ role.name }}</span>
                    <Icon v-if="role.is_admin" name="heroicon-o-shield-check" class="h-4 w-4 text-violet-500 dark:text-violet-400" />
                </label>
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
            <template v-if="canUpdate">
                <button type="submit" class="btn-primary" data-submit-button>
                    <svg data-loading-spinner class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span data-loading-label class="hidden">提交中…</span>
                    <span data-label class="inline-flex items-center gap-1.5">
                        <Icon :name="mode === 'create' ? 'heroicon-o-plus' : 'heroicon-o-check'" class="h-4 w-4" />
                        {{ mode === 'create' ? '创建用户' : '保存修改' }}
                    </span>
                </button>
            </template>
            <p v-else class="text-sm text-amber-600 dark:text-amber-400">当前角色没有「编辑用户」权限，仅可查看。</p>
            <a :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
