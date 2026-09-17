@props([
    'lines' => 3,
    'class' => '',
    'rounded' => 'rounded-lg',
])

<div {{ $attributes->merge(['class' => 'animate-pulse space-y-2']) }}>
    @for ($i = 0; $i < (int) $lines; $i++)
        <div class="h-4 {{ $rounded }} bg-gray-200 dark:bg-gray-700 {{ $class }}"></div>
    @endfor
</div>
