<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建菜单 / 权限节点" description="保存后会自动创建对应权限记录，可直接在角色管理中勾选" :back-url="route('menus.index')">
            <x-slot name="actions">
                <a href="{{ route('menus.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="card">
        <form method="POST" action="{{ route('menus.store') }}" class="p-6 space-y-6">
            @csrf

            @include('menus.partials.form')

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="创建节点" icon="heroicon-o-check" />
                <a href="{{ route('menus.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
