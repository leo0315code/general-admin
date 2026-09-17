@props([
    'icon' => 'heroicon-o-inbox',
    'title' => '暂无数据',
    'description' => null,
    'colspan' => null,
])

@if ($colspan !== null)
    {{-- 表格空态：整体放进 <tr><td :colspan> --}}
    <tr>
        <td colspan="{{ $colspan }}" class="px-5 py-12 text-center">
            <div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center']) }}>
                <x-icon :name="$icon" class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
                <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
                @if ($description)
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $description }}</p>
                @endif
                @isset($action)
                    <div class="mt-4">{{ $action }}</div>
                @endisset
            </div>
        </td>
    </tr>
@else
    {{-- 块级空态：独立容器 --}}
    <div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
        <x-icon :name="$icon" class="h-10 w-10 text-gray-300 dark:text-gray-600" />
        <p class="mt-3 text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
        @if ($description)
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $description }}</p>
        @endif
        @isset($action)
            <div class="mt-4">{{ $action }}</div>
        @endisset
    </div>
@endif
