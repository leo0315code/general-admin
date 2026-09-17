@props([
    'title' => '',
    'description' => null,
    'backUrl' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3']) }}>
    <div>
        <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @isset($actions)
            {{ $actions }}
        @endisset

        @if ($backUrl)
            <a href="{{ $backUrl }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        @endif
    </div>
</div>
