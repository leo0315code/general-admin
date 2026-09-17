<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑角色：{{ $role->name }}" description="修改角色信息与权限分配" :back-url="route('roles.index')">
            <x-slot name="actions">
                <a href="{{ route('roles.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    <div class="card">
        <form method="POST" action="{{ route('roles.update', $role) }}" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            <x-form-field name="name" label="角色标识" :required="true" hint="{{ $role->name === \App\Models\User::ROLE_ADMIN ? '内置 admin 角色的标识不允许修改。' : null }}">
                <input id="name" name="name" type="text" class="input @error('name') input-error @enderror" value="{{ old('name', $role->name) }}" @readonly($role->name === \App\Models\User::ROLE_ADMIN) required>
            </x-form-field>

            <x-form-field name="description" label="描述">
                <textarea id="description" name="description" rows="2" class="input @error('description') input-error @enderror" placeholder="角色职责说明（选填）">{{ old('description', $role->description) }}</textarea>
            </x-form-field>

            {{-- 权限分配：按菜单树勾选（目录 → 菜单 → 按钮） --}}
            @include('roles.partials.permission-picker')

            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="保存修改" icon="heroicon-o-check" />
                @if ($role->name !== \App\Models\User::ROLE_ADMIN)
                    <form method="POST" action="{{ route('roles.destroy', $role) }}"
                          data-confirm-title="确定要删除角色「{{ $role->name }}」吗？"
                          data-confirm-message="删除后该角色及其权限分配将一并移除。">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                                @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))">
                            <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                            删除角色
                        </button>
                    </form>
                @endif
            </div>
        </form>
    </div>

    <x-confirm-modal />
</x-app-layout>
