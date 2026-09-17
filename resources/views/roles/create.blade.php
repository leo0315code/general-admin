<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建角色" description="创建角色并分配权限" />
    </x-slot>

    <div class="card">
        <form method="POST" action="{{ route('roles.store') }}" class="p-6 space-y-6">
            @csrf

            <x-form-field name="name" label="角色标识" :required="true" hint="小写英文标识，用于代码中的角色判断（如 admin / editor）。">
                <input id="name" name="name" type="text" class="input @error('name') input-error @enderror" value="{{ old('name') }}" placeholder="如：operator（小写英文，用于代码判断）" required autofocus>
            </x-form-field>

            <x-form-field name="description" label="描述">
                <textarea id="description" name="description" rows="2" class="input @error('description') input-error @enderror" placeholder="角色职责说明（选填）">{{ old('description') }}</textarea>
            </x-form-field>

            {{-- 权限分配：按菜单树勾选（目录 → 菜单 → 按钮） --}}
            @include('roles.partials.permission-picker')

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="创建角色" icon="heroicon-o-plus" />
                <a href="{{ route('roles.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
