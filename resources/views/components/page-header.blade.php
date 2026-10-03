@props([
    'title' => '',
    'description' => null,
    'backUrl' => null,
    // 返回按钮文案：默认「返回列表」，列表页可自定义为「返回用户列表」等
    // 注意：传了 back-url 就会自动渲染返回按钮，页面无需再在 actions 里重复放一个
    'backLabel' => '返回列表',
    // 面包屑：[['label' => '文章管理', 'url' => route('posts.index')], ['label' => '新建文章']]
    // 最后一项不带 url 即当前页；首个「首页」链接自动生成，无需传
    'breadcrumbs' => [],
])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3']) }}>
    <div>
        @if (! empty($breadcrumbs))
            <nav class="mb-1.5" aria-label="面包屑">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs">
                    <li>
                        <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-primary-600 dark:text-gray-500 dark:hover:text-primary-400 transition">首页</a>
                    </li>
                    @foreach ($breadcrumbs as $crumb)
                        <li class="flex items-center gap-x-1.5">
                            <span class="text-gray-300 dark:text-gray-600" aria-hidden="true">/</span>
                            @if (! empty($crumb['url']))
                                <a href="{{ $crumb['url'] }}" class="text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400 transition">{{ $crumb['label'] }}</a>
                            @else
                                <span class="font-medium text-gray-700 dark:text-gray-200" aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
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
                {{ $backLabel }}
            </a>
        @endif
    </div>
</div>
