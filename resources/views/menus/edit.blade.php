<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑节点：{{ $menu->title }}" description="{{ $menu->typeLabel() }} · 权限标识 {{ $menu->permission_name ?? '（未配置）' }}" :back-url="route('menus.index')">
            <x-slot name="actions">
                <a href="{{ route('menus.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="card">
        <form method="POST" action="{{ route('menus.update', $menu) }}" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            @include('menus.partials.form')

            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="保存修改" icon="heroicon-o-check" />
                <a href="{{ route('menus.index') }}" class="btn-secondary">取消</a>

                @can('menus.destroy')
                    <button
                        type="submit"
                        form="menu-destroy-form"
                        class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2 ml-auto"
                        @click.prevent="Alpine.store('confirmModal').open(document.getElementById('menu-destroy-form'))"
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
                data-confirm-title="确定要删除「{{ $menu->title }}」吗？"
                data-confirm-message="对应权限记录将一并清理。"
            >
                @csrf
                @method('DELETE')
            </form>
        @endcan
    </div>

    <x-confirm-modal />
</x-app-layout>
