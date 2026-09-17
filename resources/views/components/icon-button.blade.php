@props([
    'icon' => null,
    'title' => '',
    'variant' => 'ghost', // ghost | danger | primary
    'href' => null,
])

@php
    $variants = [
        'ghost' => 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white',
        'danger' => 'text-danger-600 dark:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-500/10',
        'primary' => 'text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10',
    ];
    $classes = 'btn-icon '.($variants[$variant] ?? $variants['ghost']);
@endphp

@if ($href)
    <a
        href="{{ $href }}"
        title="{{ $title }}"
        aria-label="{{ $title }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4" />
        @else
            {{ $slot }}
        @endif
    </a>
@else
    <button
        type="button"
        title="{{ $title }}"
        aria-label="{{ $title }}"
        {{ $attributes->merge(['class' => $classes]) }}
    >
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4" />
        @else
            {{ $slot }}
        @endif
    </button>
@endif
