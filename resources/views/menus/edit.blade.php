<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">编辑节点：{{ $menu->title }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $menu->typeLabel() }} · 权限标识 {{ $menu->permission_name ?? '（未配置）' }}</p>
            </div>
            <a href="{{ route('menus.index') }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        </div>
    </x-slot>

    <div class="card">
        <form method="POST" action="{{ route('menus.update', $menu) }}" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            @include('menus.partials.form')

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    保存修改
                </button>
                <a href="{{ route('menus.index') }}" class="btn-secondary">取消</a>

                @can('menus.destroy')
                    <button
                        type="submit"
                        form="menu-destroy-form"
                        class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2 ml-auto"
                    >
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        删除节点
                    </button>
                @endcan
            </div>
        </form>

        @can('menus.destroy')
            <form
                id="menu-destroy-form"
                method="POST"
                action="{{ route('menus.destroy', $menu) }}"
                onsubmit="return confirm('确定要删除「{{ $menu->title }}」吗？对应权限记录将一并清理。');"
            >
                @csrf
                @method('DELETE')
            </form>
        @endcan
    </div>
</x-app-layout>
