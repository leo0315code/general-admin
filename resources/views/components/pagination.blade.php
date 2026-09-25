@props([
    'paginator' => null,
    'pageName' => 'page',
])

@php
    if (! $paginator) {
        return;
    }
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $total = $paginator->total();
    $from = $paginator->firstItem();
    $to = $paginator->lastItem();
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
    $pages = range($start, $end);
    // 跳页 URL：保留全部现有 query，仅替换 page 占位
    // 注意：必须锚定 [?&]，否则 per_page=10 会被误替换成 per___PAGE__
    $jumpUrl = $paginator->url($current);
    $jumpUrl = preg_replace('/([?&])'.preg_quote($pageName, '/').'=\d+/', '$1'.$pageName.'=__PAGE__', $jumpUrl);
    if (! str_contains($jumpUrl, '__PAGE__')) {
        $jumpUrl .= (str_contains($jumpUrl, '?') ? '&' : '?').$pageName.'=__PAGE__';
    }
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="text-sm text-gray-500 dark:text-gray-400">
        共 <span class="font-medium text-gray-700 dark:text-gray-200">{{ $total }}</span> 条
        @if ($from !== null)
            · 显示 {{ $from }}–{{ $to }} 条
        @endif
    </div>

    {{-- 跳页：原生 JS（见 window.Layout.initPagination），不再依赖 Alpine --}}
    <div
        class="flex flex-wrap items-center gap-1"
        data-pagination
        data-current="{{ $current }}"
        data-last="{{ $last }}"
        data-jump-url="{{ $jumpUrl }}"
    >
        {{-- 上一页 --}}
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-sm text-gray-300 dark:text-gray-600 cursor-not-allowed select-none" aria-disabled="true">
                <x-icon name="heroicon-o-chevron-left" class="h-4 w-4" />
                上一页
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                <x-icon name="heroicon-o-chevron-left" class="h-4 w-4" />
                上一页
            </a>
        @endif

        {{-- 页码 --}}
        @if ($start > 1)
            <a href="{{ $paginator->url(1) }}" class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">1</a>
            @if ($start > 2)
                <span class="px-1 text-gray-400 dark:text-gray-500 select-none">…</span>
            @endif
        @endif

        @foreach ($pages as $p)
            @if ($p === $current)
                <span class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-lg text-sm font-semibold bg-primary-600 text-white shadow-card" aria-current="page">{{ $p }}</span>
            @else
                <a href="{{ $paginator->url($p) }}" class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">{{ $p }}</a>
            @endif
        @endforeach

        @if ($end < $last)
            @if ($end < $last - 1)
                <span class="px-1 text-gray-400 dark:text-gray-500 select-none">…</span>
            @endif
            <a href="{{ $paginator->url($last) }}" class="inline-flex items-center justify-center min-w-[2rem] h-8 px-2 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">{{ $last }}</a>
        @endif

        {{-- 下一页 --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                下一页
                <x-icon name="heroicon-o-chevron-right" class="h-4 w-4" />
            </a>
        @else
            <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-sm text-gray-300 dark:text-gray-600 cursor-not-allowed select-none" aria-disabled="true">
                下一页
                <x-icon name="heroicon-o-chevron-right" class="h-4 w-4" />
            </span>
        @endif

        {{-- 跳页 --}}
        <div class="flex items-center gap-1 ml-1.5">
            <span class="text-xs text-gray-400 dark:text-gray-500 select-none">跳至</span>
            <input
                type="number"
                min="1"
                max="{{ $last }}"
                value="{{ $current }}"
                data-page-jump
                class="input !w-16 !px-2 !py-1 text-center text-sm"
                aria-label="跳转页码"
            >
            <button type="button" data-page-goto class="btn-secondary !px-2.5 !py-1 text-xs">GO</button>
        </div>
    </div>
</div>
