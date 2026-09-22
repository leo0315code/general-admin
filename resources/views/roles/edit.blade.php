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

    @php
    $isAdminRole = $role->name === \App\Models\User::ROLE_ADMIN;
    $roleFormProps = [
        'mode' => 'edit',
        'action' => route('roles.update', $role),
        'method' => 'PATCH',
        'csrf' => csrf_token(),
        'old' => [
            'name' => old('name', $role->name),
            'description' => old('description', $role->description),
            'permissions' => old('permissions', $rolePermissionIds),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('roles.index'),
        'destroyUrl' => route('roles.destroy', $role),
        'nameReadonly' => $isAdminRole,
        'canDestroy' => ! $isAdminRole,
        'permissionRows' => collect(\App\Models\Menu::flatten())->map(fn ($row) => [
            'id' => $row['menu']->id,
            'title' => $row['menu']->title,
            'type' => $row['menu']->type,
            'type_label' => $row['menu']->typeLabel(),
            'permission_name' => $row['menu']->permission_name,
            'permission_id' => $row['menu']->permission_name ? ($permissionIds[$row['menu']->permission_name] ?? null) : null,
            'status' => (bool) $row['menu']->status,
            'depth' => $row['depth'],
            'group_ids' => array_values($groupPermissionIds[$row['menu']->id] ?? []),
        ])->values(),
    ];
@endphp

    <div class="card">
        <div
            data-vue-app
            data-component="role-form"
            data-props='{!! vue_props($roleFormProps) !!}'
            x-ignore
        ></div>
    </div>
</x-app-layout>
