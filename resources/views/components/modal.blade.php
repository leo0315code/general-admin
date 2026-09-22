@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
][$maxWidth];
@endphp

{{-- 通用弹窗（原生 JS 版，替代 Breeze x-modal）：
     触发 window.dispatchEvent(new CustomEvent('open-modal', { detail: '名称' }))
     关闭：data-modal-close 元素点击 / Escape / window close-modal 事件
     焦点处理与 body 锁定见 window.Layout.initModal --}}
<div
    data-modal="{{ $name }}"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50 {{ $show ? '' : 'hidden' }}"
    style="{{ $show ? '' : 'display: none;' }}"
>
    <div class="fixed inset-0 transform transition-all" data-modal-close>
        <div class="absolute inset-0 bg-gray-500 dark:bg-gray-900 opacity-75"></div>
    </div>

    <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto">
        {{ $slot }}
    </div>
</div>
