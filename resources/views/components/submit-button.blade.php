@props([
    'label' => '提交',
    'icon' => null,
    'loadingText' => '提交中…',
    'variant' => 'primary', // primary | danger | secondary
    'type' => 'submit',
])

@php
    $classes = match ($variant) {
        'danger' => 'inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-danger-600 hover:bg-danger-500 active:bg-danger-700 text-white text-sm font-medium rounded-control transition shadow-card',
        'secondary' => 'btn-secondary',
        default => 'btn-primary',
    };
@endphp

{{-- 提交按钮（原生 JS）：点击后等浏览器完成 HTML5 约束校验再进入 loading；
     校验未通过时（如必填项为空）表单不会提交，按钮也不能锁死转圈（见 window.Layout.initSubmitButtons） --}}
<button
    type="{{ $type }}"
    data-submit-button
    {{ $attributes->merge(['class' => $classes]) }}
>
    <svg data-loading-spinner class="hidden animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>
    <span data-loading-label class="hidden">{{ $loadingText }}</span>
    <span data-label class="inline-flex items-center gap-1.5">
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $label }}
    </span>
</button>
