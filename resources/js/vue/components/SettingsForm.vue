<script setup>
// 系统设置表单 —— 站点名称 / 列表每页条数 / 版权信息
// 原生 PUT 提交 + 服务端错误回显（字段由后端 SettingController::DEFAULTS 下发）
import { reactive } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    action: { type: String, default: '' },
    method: { type: String, default: 'PUT' },
    csrf: { type: String, default: '' },
    // [{ key, label, value, type: 'text'|'number', placeholder, min, max, hint }]
    fields: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    indexUrl: { type: String, default: '' },
});

const values = reactive({});
props.fields.forEach((f) => {
    values[f.key] = f.value ?? '';
});

function fieldError(key) {
    return props.errors[key] || [];
}
</script>

<template>
    <form :action="action" method="POST" class="p-6 space-y-6" novalidate>
        <input type="hidden" name="_token" :value="csrf">
        <input v-if="method !== 'POST'" type="hidden" name="_method" :value="method">

        <div v-for="field in fields" :key="field.key">
            <label class="label" :for="field.key">
                {{ field.label }}
                <span v-if="field.type !== 'number'" class="text-danger-500">*</span>
            </label>

            <input
                v-if="field.type === 'number'"
                :id="field.key"
                :name="field.key"
                v-model="values[field.key]"
                type="number"
                class="input"
                :class="{ 'input-error': fieldError(field.key).length }"
                :min="field.min"
                :max="field.max"
            >
            <input
                v-else
                :id="field.key"
                :name="field.key"
                v-model="values[field.key]"
                type="text"
                class="input"
                :class="{ 'input-error': fieldError(field.key).length }"
                :placeholder="field.placeholder || ''"
            >

            <p v-if="field.hint" class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ field.hint }}</p>
            <p v-for="e in fieldError(field.key)" :key="e" class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ e }}</p>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">保存后立即生效（站点名称用于后台品牌展示与页面标题）。</p>

        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
            <button type="submit" class="btn-primary" data-submit-button>
                <svg data-loading-spinner class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span data-loading-label class="hidden">保存中…</span>
                <span data-label class="inline-flex items-center gap-1.5">
                    <Icon name="heroicon-o-check" class="h-4 w-4" />
                    保存设置
                </span>
            </button>

            <a v-if="indexUrl" :href="indexUrl" class="btn-secondary">取消</a>
        </div>
    </form>
</template>
