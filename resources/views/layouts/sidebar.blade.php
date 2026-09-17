{{-- 左侧边栏菜单：由 menus 表动态生成（菜单即权限），权限控制显隐，用户信息在顶栏下拉展示 --}}
@php
    $navGroups = \App\Support\Navigation::forUser(Auth::user());
@endphp

<div class="flex flex-col h-full py-4">
    <nav class="flex-1 px-3 space-y-0.5">
        @foreach ($navGroups as $group)
            @if ($group['title'])
                <div class="px-3 pb-1.5 {{ $loop->first ? 'pt-1' : 'pt-4' }} text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">{{ $group['title'] }}</div>
            @endif

            @foreach ($group['items'] as $item)
                @if ($item['url'])
                    <a
                        href="{{ $item['url'] }}"
                        @class([
                            'relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition',
                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-600/90 dark:text-white' => $item['active'],
                            'text-gray-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white' => ! $item['active'],
                        ])
                    >
                        @if ($item['icon'])
                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                        @else
                            <span class="h-5 w-5 shrink-0 rounded bg-gray-200 dark:bg-slate-700"></span>
                        @endif
                        {{ $item['title'] }}
                    </a>
                @else
                    <span class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-400 dark:text-slate-500 cursor-not-allowed" title="该菜单未配置有效路由">
                        @if ($item['icon'])
                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                        @else
                            <span class="h-5 w-5 shrink-0 rounded bg-gray-200 dark:bg-slate-700"></span>
                        @endif
                        {{ $item['title'] }}
                    </span>
                @endif
            @endforeach
        @endforeach
    </nav>

    {{-- 底部：退出登录 --}}
    <div class="px-3 mt-4 pt-4 border-t border-gray-200 dark:border-slate-800">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-gray-500 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-800 hover:text-gray-900 dark:hover:text-white transition"
            >
                <x-icon name="heroicon-o-arrow-right-on-rectangle" class="h-5 w-5 shrink-0" />
                退出登录
            </button>
        </form>
    </div>
</div>
