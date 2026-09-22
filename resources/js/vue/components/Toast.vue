<script setup>
// 全局 Toast（右上角堆叠，3s 自动消失）
// 由 AppShell 转发 window 事件 app:toast 驱动；亦可直接 import 后调用
import { ref } from 'vue';
import Icon from './Icon.vue';

const items = ref([]);
let nextId = 1;

function show(type, message, duration = 3000) {
    const id = nextId++;
    items.value.push({ id, type: type === 'error' ? 'error' : 'success', message, visible: false });

    requestAnimationFrame(() => {
        const t = items.value.find((i) => i.id === id);
        if (t) t.visible = true;
    });

    setTimeout(() => {
        const t = items.value.find((i) => i.id === id);
        if (t) t.visible = false;
        setTimeout(() => {
            items.value = items.value.filter((i) => i.id !== id);
        }, 200);
    }, duration);
}

defineExpose({ show });
</script>

<template>
    <Teleport to="body">
        <div aria-live="polite" class="fixed top-4 right-4 sm:right-6 z-[70] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2">
            <div
                v-for="toast in items"
                :key="toast.id"
                :class="[
                    toast.visible ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-4',
                    'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-100',
                ]"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg shadow-gray-900/10 dark:shadow-black/50 ring-1 ring-black/5 dark:ring-white/10 transition-all duration-200"
            >
                <Icon
                    :name="toast.type === 'error' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle'"
                    :class="toast.type === 'error' ? 'text-red-500' : 'text-emerald-500'"
                    class="h-5 w-5 shrink-0 mt-0.5"
                />
                <p class="text-sm font-medium flex-1 leading-relaxed">{{ toast.message }}</p>
                <button
                    type="button"
                    class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition"
                    aria-label="关闭"
                    @click="items = items.filter((i) => i.id !== toast.id)"
                >
                    <Icon name="heroicon-o-x-mark" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </Teleport>
</template>
