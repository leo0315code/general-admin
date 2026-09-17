<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建菜单 / 权限节点</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">保存后会自动创建对应权限记录，可直接在角色管理中勾选</p>
            </div>
            <a href="{{ route('menus.index') }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        </div>
    </x-slot>

    <div class="card">
        <form method="POST" action="{{ route('menus.store') }}" class="p-6 space-y-6">
            @csrf

            @include('menus.partials.form')

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    创建节点
                </button>
                <a href="{{ route('menus.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
