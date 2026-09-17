@props([
    'actionUrl' => '#',
    'method' => 'POST',
    'confirmTitle' => '确定执行批量操作吗？',
    'confirmMessage' => '该操作将影响所有已选中的记录。',
    'name' => 'ids[]',
])

{{--
    批量操作条（UI 现代化重构 · T03）
    ------------------------------------------------------------
    依赖父级 x-data="listSelection()" 提供的 selectedIds / selectAll / toggleAll / syncSelectAll：
    - 有选中项时才显示；
    - ids[] 由 Alpine 动态注入隐藏 input（保留 @csrf / @method）；
    - 操作按钮需 type="submit"，点击前经 x-confirm-modal 确认（真实表单提交）。
--}}
<div
    x-show="selectedIds.length > 0"
    x-cloak
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-primary-200 dark:border-primary-500/30 bg-primary-50 dark:bg-primary-500/10 px-4 py-3"
>
    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-700 dark:text-primary-300">
        <x-icon name="heroicon-o-check-circle" class="h-4 w-4" />
        已选 <span class="font-bold" x-text="selectedIds.length"></span> 项
    </span>

    <form
        method="POST"
        action="{{ $actionUrl }}"
        class="flex flex-wrap items-center gap-2"
        data-confirm-title="{{ $confirmTitle }}"
        data-confirm-message="{{ $confirmMessage }}"
    >
        @csrf
        @method($method)

        <template x-for="id in selectedIds" :key="id">
            <input type="hidden" name="{{ $name }}" :value="id">
        </template>

        {{ $slot }}

        <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs" @click="selectedIds = []; selectAll = false">
            <x-icon name="heroicon-o-x-mark" class="h-3.5 w-3.5" />
            取消选择
        </button>
    </form>
</div>
