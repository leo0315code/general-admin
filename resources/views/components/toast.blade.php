{{--
    全局 Toast 容器（UI 现代化重构 · T05）
    依赖 Alpine.store('toast')：show(type, message) / success / error。
    挂载于 layouts/app.blade.php，全站单例。
--}}
<div
    x-data
    x-cloak
    aria-live="polite"
    class="fixed top-20 right-4 sm:right-6 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"
>
    <template x-for="(toast, index) in $store.toast.items" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-4"
            class="pointer-events-auto flex items-start gap-2.5 rounded-lg border px-4 py-3 shadow-popover"
            :class="toast.type === 'error'
                ? 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300'
                : 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300'"
        >
            <x-icon x-show="toast.type === 'error'" name="heroicon-o-x-circle" class="h-5 w-5 shrink-0 mt-0.5" />
            <x-icon x-show="toast.type !== 'error'" name="heroicon-o-check-circle" class="h-5 w-5 shrink-0 mt-0.5" />
            <p class="text-sm flex-1" x-text="toast.message"></p>
            <button type="button" @click="$store.toast.items.splice(index, 1)" class="shrink-0 opacity-60 hover:opacity-100 transition" aria-label="关闭">
                <x-icon name="heroicon-o-x-mark" class="h-4 w-4" />
            </button>
        </div>
    </template>
</div>
