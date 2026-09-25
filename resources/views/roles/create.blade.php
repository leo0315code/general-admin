<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建角色" description="创建角色并分配权限" />
    </x-slot>

    @php
    $roleFormProps = [
        'mode' => 'create',
        'action' => route('roles.store'),
        'method' => 'POST',
        'csrf' => csrf_token(),
        'old' => [
            'name' => old('name', ''),
            'description' => old('description', ''),
            'permissions' => old('permissions', []),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('roles.index'),
        'nameReadonly' => false,
        'canDestroy' => false,
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
        <x-vue-mount component="role-form" :props="$roleFormProps" />
    </div>
</x-app-layout>
