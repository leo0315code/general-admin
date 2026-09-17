{{-- 权限分配：按菜单树勾选（目录 → 菜单 → 按钮），与「菜单管理」同源 --}}
<div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <label class="label mb-0">权限分配</label>
        <label class="inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 cursor-pointer">
            <input
                type="checkbox"
                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                onchange="toggleAllPermissions(this)"
            >
            全选 / 取消全选
        </label>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">按菜单树勾选该角色可访问的菜单与按钮；admin 角色自动拥有全部权限。菜单与权限同源，维护入口在「菜单管理」。</p>

    <div class="space-y-3">
        @foreach ($menuTree as $node)
            @include('roles.partials.permission-tree', [
                'node' => $node,
                'depth' => 0,
                'permissionIds' => $permissionIds,
                'rolePermissionIds' => $rolePermissionIds ?? [],
                'groupPermissionIds' => $groupPermissionIds,
            ])
        @endforeach
    </div>

    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
</div>

<script>
    function toggleAllPermissions(source) {
        document.querySelectorAll('input[name="permissions[]"]').forEach(function (box) {
            box.checked = source.checked;
        });
    }

    function togglePermissionGroup(source, ids) {
        document.querySelectorAll('input[name="permissions[]"]').forEach(function (box) {
            if (ids.indexOf(Number(box.value)) !== -1) {
                box.checked = source.checked;
            }
        });
    }
</script>
