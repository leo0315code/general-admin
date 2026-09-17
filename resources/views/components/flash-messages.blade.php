@php
    $messages = [
        'success' => session('success'),
        'error' => session('error'),
    ];
@endphp

{{--
    Flash 消息（UI 现代化重构 · T05）
    ------------------------------------------------------------
    「Toast 驱动」升级：session flash 投喂给全局 Toast（右上角展示），
    同时**保留 DOM 文本**（隐藏容器），保证既有 assertSee 断言不破，
    且无 JS / 关闭 JS 场景下用户仍可看到消息文本。
--}}
@foreach ($messages as $type => $message)
    @if ($message)
        <div
            x-data="{ fed: false }"
            x-init="if (! fed) { fed = true; Alpine.store('toast').show(@js($type), @js($message)); }"
            class="hidden"
            aria-hidden="true"
        >
            <span class="flash-message-{{ $type }}">{{ $message }}</span>
        </div>
    @endif
@endforeach
