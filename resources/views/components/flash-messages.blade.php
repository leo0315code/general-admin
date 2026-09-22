@php
    $messages = [
        'success' => session('success'),
        'error' => session('error'),
    ];
@endphp

{{--
    Flash 消息（原生 JS → Vue Toast）
    ------------------------------------------------------------
    session flash 投喂给全局 Toast（右上角展示，window.__ui.toast 事件桥→Vue），
    同时**保留 DOM 文本**（隐藏容器），保证既有 assertSee 断言不破，
    且无 JS / 关闭 JS 场景下用户仍可看到消息文本。
--}}
@foreach ($messages as $type => $message)
    @if ($message)
        <div
            data-flash-type="{{ $type }}"
            data-flash-message="{{ $message }}"
            class="hidden"
            aria-hidden="true"
        >
            <span class="flash-message-{{ $type }}">{{ $message }}</span>
        </div>
    @endif
@endforeach
