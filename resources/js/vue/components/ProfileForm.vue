<script setup>
// 个人资料页 —— 三张卡片：基本信息 / 修改密码 / 注销账号
// 全部原生表单提交（PATCH / PUT / DELETE），错误来自服务端 error bag
// 注销账号需输入当前密码：Vue 内联弹窗（不依赖 Alpine x-modal）
import { ref } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    csrf: { type: String, default: '' },
    user: { type: Object, default: () => ({ name: '', email: '' }) },
    unverified: { type: Boolean, default: false },
    updateUrl: { type: String, default: '' },
    passwordUrl: { type: String, default: '' },
    destroyUrl: { type: String, default: '' },
    verificationUrl: { type: String, default: '' },
    // { default: {}, updatePassword: {}, userDeletion: {} }
    errors: { type: Object, default: () => ({}) },
    // session status：profile-updated / password-updated / verification-link-sent
    status: { type: String, default: '' },
});

const name = ref(props.user.name ?? '');
const email = ref(props.user.email ?? '');

const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');

const showDeleteModal = ref(false);
const deletePassword = ref('');

const savedHint = ref(props.status === 'profile-updated' || props.status === 'password-updated');
if (savedHint.value) {
    setTimeout(() => (savedHint.value = false), 2500);
}

function bagErrors(bag, field) {
    return props.errors?.[bag]?.[field] || [];
}

function infoError(field) {
    return props.errors?.default?.[field] || props.errors?.[field] || [];
}

function openDeleteModal() {
    deletePassword.value = '';
    showDeleteModal.value = true;
    document.body.classList.add('overflow-y-hidden');
}

function closeDeleteModal() {
    showDeleteModal.value = false;
    document.body.classList.remove('overflow-y-hidden');
}

function submitDelete() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = props.destroyUrl;
    form.innerHTML =
        `<input type="hidden" name="_token" value="${props.csrf}">` +
        `<input type="hidden" name="_method" value="DELETE">` +
        `<input type="hidden" name="password" value="${deletePassword.value.replace(/"/g, '&quot;')}">`;
    document.body.appendChild(form);
    closeDeleteModal();
    form.submit();
}
</script>

<template>
    <div class="space-y-6 max-w-3xl">
        <!-- ① 基本信息 -->
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">基本信息</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">修改账号昵称与登录邮箱；邮箱变更后需重新验证。</p>
            </div>

            <form :action="updateUrl" method="POST" class="p-6 space-y-5" novalidate>
                <input type="hidden" name="_token" :value="csrf">
                <input type="hidden" name="_method" value="PATCH">

                <div>
                    <label class="label" for="name">昵称 <span class="text-danger-500">*</span></label>
                    <input
                        id="name"
                        name="name"
                        v-model="name"
                        type="text"
                        class="input"
                        :class="{ 'input-error': infoError('name').length }"
                        autocomplete="name"
                        required
                    >
                    <p v-for="e in infoError('name')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                </div>

                <div>
                    <label class="label" for="email">登录邮箱 <span class="text-danger-500">*</span></label>
                    <input
                        id="email"
                        name="email"
                        v-model="email"
                        type="email"
                        class="input"
                        :class="{ 'input-error': infoError('email').length }"
                        autocomplete="username"
                        required
                    >
                    <p v-for="e in infoError('email')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>

                    <div v-if="unverified" class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        邮箱尚未验证。
                        <form :action="verificationUrl" method="POST" class="inline">
                            <input type="hidden" name="_token" :value="csrf">
                            <button type="submit" class="underline text-primary-600 dark:text-primary-400 hover:text-primary-500">
                                点击重新发送验证邮件
                            </button>
                        </form>
                        <span v-if="status === 'verification-link-sent'" class="ml-2 font-medium text-emerald-600 dark:text-emerald-400">
                            验证邮件已发送。
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <button type="submit" class="btn-primary" data-submit-button>
                        <svg data-loading-spinner class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span data-loading-label class="hidden">保存中…</span>
                        <span data-label class="inline-flex items-center gap-1.5">
                            <Icon name="heroicon-o-check" class="h-4 w-4" />
                            保存资料
                        </span>
                    </button>
                    <span v-if="savedHint && status === 'profile-updated'" class="text-sm text-gray-500 dark:text-gray-400">已保存。</span>
                </div>
            </form>
        </div>

        <!-- ② 修改密码 -->
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">修改密码</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">建议使用足够长且随机的密码，避免与其他站点重复。</p>
            </div>

            <form :action="passwordUrl" method="POST" class="p-6 space-y-5" novalidate>
                <input type="hidden" name="_token" :value="csrf">
                <input type="hidden" name="_method" value="PUT">

                <div>
                    <label class="label" for="current_password">当前密码 <span class="text-danger-500">*</span></label>
                    <input
                        id="current_password"
                        name="current_password"
                        v-model="currentPassword"
                        type="password"
                        class="input"
                        :class="{ 'input-error': bagErrors('updatePassword', 'current_password').length }"
                        autocomplete="current-password"
                    >
                    <p v-for="e in bagErrors('updatePassword', 'current_password')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                </div>

                <div>
                    <label class="label" for="password">新密码 <span class="text-danger-500">*</span></label>
                    <input
                        id="password"
                        name="password"
                        v-model="newPassword"
                        type="password"
                        class="input"
                        :class="{ 'input-error': bagErrors('updatePassword', 'password').length }"
                        autocomplete="new-password"
                    >
                    <p v-for="e in bagErrors('updatePassword', 'password')" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
                </div>

                <div>
                    <label class="label" for="password_confirmation">确认新密码 <span class="text-danger-500">*</span></label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        v-model="confirmPassword"
                        type="password"
                        class="input"
                        autocomplete="new-password"
                    >
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <button type="submit" class="btn-primary" data-submit-button>
                        <svg data-loading-spinner class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span data-loading-label class="hidden">保存中…</span>
                        <span data-label class="inline-flex items-center gap-1.5">
                            <Icon name="heroicon-o-lock-closed" class="h-4 w-4" />
                            更新密码
                        </span>
                    </button>
                    <span v-if="savedHint && status === 'password-updated'" class="text-sm text-gray-500 dark:text-gray-400">已保存。</span>
                </div>
            </form>
        </div>

        <!-- ③ 注销账号 -->
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-gray-900 dark:text-gray-100">注销账号</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    账号注销后，其全部资料与数据将被永久删除且无法恢复，请先备份需要保留的内容。
                </p>
            </div>

            <div class="p-6">
                <button
                    type="button"
                    class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                    @click="openDeleteModal"
                >
                    <Icon name="heroicon-o-trash" class="h-4 w-4" />
                    注销账号
                </button>
                <p v-for="e in bagErrors('userDeletion', 'password')" :key="e" class="mt-2 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
            </div>
        </div>

        <!-- 注销确认弹窗（需输入当前密码） -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-500/60 dark:bg-black/60" @click="closeDeleteModal"></div>

                <div class="relative mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl ring-1 ring-black/10 dark:ring-gray-600/70 sm:w-full sm:max-w-md sm:mx-auto mt-24 sm:mt-32">
                    <form class="p-6" @submit.prevent="submitDelete">
                        <div class="flex items-start gap-4">
                            <span class="inline-flex items-center justify-center h-11 w-11 rounded-full shrink-0 bg-danger-100 text-danger-600 dark:bg-danger-500/20 dark:text-danger-400">
                                <Icon name="heroicon-o-exclamation-triangle" class="h-6 w-6" />
                            </span>
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">确定要注销账号吗？</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    注销后所有数据将被永久删除。请输入当前密码以确认此操作。
                                </p>

                                <div class="mt-4">
                                    <label class="label" for="delete_password">当前密码</label>
                                    <input
                                        id="delete_password"
                                        v-model="deletePassword"
                                        type="password"
                                        class="input"
                                        placeholder="请输入当前密码"
                                        autocomplete="current-password"
                                        autofocus
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3">
                            <button type="button" class="btn-secondary" @click="closeDeleteModal">
                                <Icon name="heroicon-o-x-mark" class="h-4 w-4" />
                                取消
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-danger-600 hover:bg-danger-500 active:bg-danger-700 text-white text-sm font-medium rounded-control transition shadow-card disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="!deletePassword"
                            >
                                <Icon name="heroicon-o-trash" class="h-4 w-4" />
                                确认注销
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </div>
</template>
