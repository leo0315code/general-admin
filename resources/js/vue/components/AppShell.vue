<script setup>
// 全局 Vue 交互壳（挂在主布局底部，全站单例）
// 职责：渲染 Vue 版 Toast / ConfirmModal，并把 Alpine store 桥接发来的
// window 事件（app:toast / app:confirm / app:confirm-close）转发给子组件。
// 同时消费 Alpine 初始化期间（Vue 挂载前）缓冲的事件（如 flash toast）。
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ConfirmModal from './ConfirmModal.vue';
import Toast from './Toast.vue';

const props = defineProps({
    userName: { type: String, default: '' },
});

const toastRef = ref(null);
const confirmRef = ref(null);

let cleanup = null;

function handle(name, detail) {
    if (name === 'app:toast') {
        toastRef.value?.show(detail?.type, detail?.message, detail?.duration);
    } else if (name === 'app:confirm') {
        confirmRef.value?.open(detail);
    } else if (name === 'app:confirm-close') {
        confirmRef.value?.close();
    }
}

function listeners() {
    return {
        toast: (e) => handle('app:toast', e.detail),
        confirm: (e) => handle('app:confirm', e.detail),
        confirmClose: () => handle('app:confirm-close'),
    };
}

onMounted(() => {
    // 消费 Alpine 初始化期间的缓冲事件（flash toast 等，5 秒内）
    const buffer = window.__appEvents || [];
    window.__appEvents = [];
    for (const ev of buffer) {
        if (Date.now() - ev.time < 5000) handle(ev.name, ev.detail);
    }

    const ls = listeners();
    window.addEventListener('app:toast', ls.toast);
    window.addEventListener('app:confirm', ls.confirm);
    window.addEventListener('app:confirm-close', ls.confirmClose);
    cleanup = ls;
});

onBeforeUnmount(() => {
    if (cleanup) {
        window.removeEventListener('app:toast', cleanup.toast);
        window.removeEventListener('app:confirm', cleanup.confirm);
        window.removeEventListener('app:confirm-close', cleanup.confirmClose);
        cleanup = null;
    }
});
</script>

<template>
    <Toast ref="toastRef" />
    <ConfirmModal ref="confirmRef" />
</template>
