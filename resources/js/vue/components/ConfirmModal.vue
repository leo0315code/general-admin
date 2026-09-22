<script setup>
// 全局确认弹窗（替代原 Blade x-confirm-modal）
// 触发：window.dispatchEvent(new CustomEvent('app:confirm', { detail: { form, title, message, confirmText, cancelText, variant } }))
// 确认后 form.submit()（真实表单，CSRF/Gate 不变）
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from './Icon.vue';

const visible = ref(false);
const data = ref({
    form: null,
    title: '确定继续吗？',
    message: '',
    confirmText: '确认',
    cancelText: '取消',
    variant: 'danger',
});

function open(detail = {}) {
    data.value = { ...data.value, ...detail };
    visible.value = true;
    document.body.classList.add('overflow-y-hidden');
}

function close() {
    visible.value = false;
    document.body.classList.remove('overflow-y-hidden');
}

function confirm() {
    const form = data.value.form;
    close();
    if (form) form.submit();
}

function onKeydown(e) {
    if (e.key === 'Escape' && visible.value) close();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('overflow-y-hidden');
});

defineExpose({ open, close });
</script>

<template>
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
            <!-- 遮罩（纯黑半透明，把页面明显压暗） -->
            <div class="fixed inset-0 bg-gray-500/60 dark:bg-black/60" @click="close"></div>

            <!-- 弹窗主体：relative 使其绘制在遮罩之上（static 会被 fixed 遮罩盖住变灰） -->
            <div class="relative mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl shadow-gray-900/30 dark:shadow-black/70 ring-1 ring-black/10 dark:ring-gray-600/70 sm:w-full sm:max-w-md sm:mx-auto mt-24 sm:mt-32">
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <span
                            :class="data.variant === 'primary' ? 'bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400' : 'bg-danger-100 text-danger-600 dark:bg-danger-500/20 dark:text-danger-400'"
                            class="inline-flex items-center justify-center h-11 w-11 rounded-full shrink-0"
                        >
                            <Icon v-if="data.variant === 'primary'" name="heroicon-o-question-mark-circle" class="h-6 w-6" />
                            <Icon v-else name="heroicon-o-exclamation-triangle" class="h-6 w-6" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 id="confirm-modal-title" class="text-base font-semibold text-gray-900 dark:text-white">{{ data.title }}</h3>
                            <p v-if="data.message" class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">{{ data.message }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-control transition" @click="close">
                            <Icon name="heroicon-o-x-mark" class="h-4 w-4" />
                            <span>{{ data.cancelText }}</span>
                        </button>
                        <button
                            type="button"
                            :class="data.variant === 'primary' ? 'btn-primary' : 'inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-danger-600 hover:bg-danger-500 active:bg-danger-700 text-white text-sm font-medium rounded-control transition shadow-card'"
                            @click="confirm"
                        >
                            <Icon name="heroicon-o-check" class="h-4 w-4" />
                            <span>{{ data.confirmText }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
