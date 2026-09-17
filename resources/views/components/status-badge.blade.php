@props([
    'type' => 'neutral', // success | warning | danger | info | neutral
    'icon' => null,
    'size' => 'sm', // sm | xs
])

@php
    $styles = [
        'success' => 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300',
        'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
        'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
        'info' => 'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300',
        'neutral' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    ];
    $sizes = [
        'sm' => 'px-2.5 py-0.5 text-xs',
        'xs' => 'px-2 py-0.5 text-[11px]',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap '.($styles[$type] ?? $styles['neutral']).' '.($sizes[$size] ?? $sizes['sm'])]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="h-3.5 w-3.5" />
    @endif
    {{ $slot }}
</span>
