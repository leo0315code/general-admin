<script setup>
// 下拉菜单（Vue 版，对应 Blade x-dropdown）
// 用法：
//   <Dropdown>
//     <template #trigger><button>菜单</button></template>
//     <a href="#">项1</a>
//   </Dropdown>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    align: { type: String, default: 'right' }, // left | right | top
    width: { type: String, default: '48' },
});

const open = ref(false);
const root = ref(null);

const widthClass = props.width === '48' ? 'w-48' : props.width;
const alignClass =
    props.align === 'left'
        ? 'origin-top-left start-0'
        : props.align === 'top'
          ? 'origin-top left-1/2 -translate-x-1/2'
          : 'origin-top-right end-0';

function onDocClick(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div ref="root" class="relative">
        <div @click="open = !open">
            <slot name="trigger" />
        </div>

        <div
            v-if="open"
            :class="[widthClass, alignClass]"
            class="absolute z-50 mt-2 rounded-md shadow-lg transition-all duration-200"
            @click="open = false"
        >
            <div class="rounded-md ring-1 ring-black ring-opacity-5 dark:ring-gray-600 py-1 bg-white dark:bg-gray-800">
                <slot />
            </div>
        </div>
    </div>
</template>
