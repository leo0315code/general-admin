@props([
    'name' => 'confirm-action',
    'confirmText' => '确认',
    'cancelText' => '取消',
    'variant' => 'danger', // danger | primary
])

@php
    $confirmClasses = $variant === 'primary'
        ? 'btn-primary'
        : 'inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-danger-600 hover:bg-danger-500 active:bg-danger-700 text-white text-sm font-medium rounded-control transition shadow-card';
@endphp

{{--
    全局确认弹窗（UI 现代化重构 · SEC-1 关键落地）
    ------------------------------------------------------------
    破坏性操作统一走此弹窗，但**不替换表单本身**：
    - 触发按钮：@click.prevent="Alpine.store('confirmModal').open($el.closest('form'))"
    - 确认后：Alpine.store('confirmModal').submit() → form.submit()
    真实表单（@csrf + @method）原样提交，后端 Gate/Policy/CSRF 校验完全不变。
    每页挂载一个即可（默认 name="confirm-action"）。
--}}
<div
    x-data="{ show: false }"
    x-on:open-confirm-modal.window="if ($event.detail.name === '{{ $name }}') show = true"
    x-on:close-confirm-modal.window="if ($event.detail.name === '{{ $name }}') show = false"
    x-on:keydown.escape.window="if (show) Alpine.store('confirmModal').close()"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
    role="dialog"
    aria-modal="true"
    aria-labelledby="confirm-modal-title"
>
    {{-- 遮罩 --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80"
        @click="Alpine.store('confirmModal').close()"
    ></div>

    {{-- 弹窗主体（缩放过渡 150–200ms） --}}
    <div
        x-show="show"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-popover transform transition-all sm:w-full sm:max-w-md sm:mx-auto mt-24 sm:mt-32"
    >
        <div class="p-6">
            <div class="flex items-start gap-4">
                <span :class="Alpine.store('confirmModal').variant === 'primary' ? 'bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-300' : 'bg-danger-100 dark:bg-danger-500/20 text-danger-600 dark:text-danger-300'"
                      class="inline-flex items-center justify-center h-11 w-11 rounded-full shrink-0">
                    <x-icon x-show="Alpine.store('confirmModal').variant === 'primary'" name="heroicon-o-question-mark-circle" class="h-6 w-6" />
                    <x-icon x-show="Alpine.store('confirmModal').variant !== 'primary'" name="heroicon-o-exclamation-triangle" class="h-6 w-6" />
                </span>
                <div class="flex-1 min-w-0">
                    <h3 id="confirm-modal-title" class="text-base font-semibold text-gray-900 dark:text-gray-100" x-text="Alpine.store('confirmModal').title"></h3>
                    <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400" x-show="Alpine.store('confirmModal').message" x-text="Alpine.store('confirmModal').message"></p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3">
                <button type="button" class="btn-secondary" @click="Alpine.store('confirmModal').close()">
                    <x-icon name="heroicon-o-x-mark" class="h-4 w-4" />
                    <span x-text="Alpine.store('confirmModal').cancelText"></span>
                </button>
                <button type="button" :class="Alpine.store('confirmModal').variant === 'primary' ? 'btn-primary' : 'inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-danger-600 hover:bg-danger-500 active:bg-danger-700 text-white text-sm font-medium rounded-control transition shadow-card'"
                        @click="Alpine.store('confirmModal').submit()">
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    <span x-text="Alpine.store('confirmModal').confirmText"></span>
                </button>
            </div>
        </div>
    </div>
</div>
