@props(['navGroups' => []])

{{-- 左侧边栏菜单：由 menus 表动态生成（菜单即权限），权限控制显隐，用户信息在顶栏下拉展示 --}}
@php
    if ($navGroups === []) {
        $navGroups = \App\Support\Navigation::forUser(Auth::user());
    }
@endphp

<div class="flex flex-col h-full py-4" x-data="sidebarGroups()">
    <nav class="flex-1 px-3 space-y-0.5">
        @foreach ($navGroups as $group)
            @if ($group['title'])
                {{-- 目录分组：可折叠，折叠状态存 localStorage --}}
                <div class="{{ $loop->first ? '' : 'pt-4' }}">
                    <button
                        type="button"
                        @click="toggle(@js($group['title']))"
                        class="flex w-full items-center justify-between px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500 hover:text-gray-600 dark:hover:text-slate-300 transition"
                        :aria-expanded="String(! isCollapsed(@js($group['title'])))"
                    >
                        <span>{{ $group['title'] }}</span>
                        <span :class="isCollapsed(@js($group['title'])) ? '-rotate-90' : ''" class="transition-transform duration-200">
                            <x-icon name="heroicon-o-chevron-down" class="h-3.5 w-3.5" />
                        </span>
                    </button>

                    <div x-show="! isCollapsed(@js($group['title']))"
                         x-transition:enter="ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @if ($item['url'])
                                <a
                                    href="{{ $item['url'] }}"
                                    @class([
                                        'relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition',
                                        'bg-primary-50 text-primary-700 dark:bg-primary-600/90 dark:text-white' => $item['active'],
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
                    </div>
                </div>
            @else
                {{-- 顶级菜单：无分组标题，独立展示 --}}
                @foreach ($group['items'] as $item)
                    @if ($item['url'])
                        <a
                            href="{{ $item['url'] }}"
                            @class([
                                'relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition',
                                'bg-primary-50 text-primary-700 dark:bg-primary-600/90 dark:text-white' => $item['active'],
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
            @endif
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
