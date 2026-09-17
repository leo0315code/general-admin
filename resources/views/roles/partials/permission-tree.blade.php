{{-- 单个权限节点的递归渲染：目录 / 菜单 / 按钮 --}}
@php
    $permissionId = $node->permission_name ? ($permissionIds[$node->permission_name] ?? null) : null;
    $childIds = $groupPermissionIds[$node->id] ?? [];
    $selected = old('permissions', $rolePermissionIds ?? []);
@endphp

<div
    class="rounded-lg border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 {{ $depth > 0 ? 'bg-gray-50/70 dark:bg-gray-900/30' : 'bg-white dark:bg-gray-800' }}"
    style="margin-left: {{ $depth * 18 }}px"
>
    <div class="flex items-center gap-2.5">
        @if ($permissionId)
            <input
                type="checkbox"
                name="permissions[]"
                value="{{ $permissionId }}"
                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                @checked(in_array($permissionId, $selected))
            >
        @else
            <span class="inline-block h-4 w-4 rounded border border-dashed border-gray-300 dark:border-gray-600" title="该节点未配置权限标识，不参与鉴权"></span>
        @endif

        <span class="text-sm {{ $depth === 0 ? 'font-semibold' : '' }} text-gray-700 dark:text-gray-200">{{ $node->title }}</span>

        <span @class([
            'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium',
            'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300' => $node->isDir(),
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => $node->isMenu(),
            'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' => $node->isButton(),
        ])>{{ $node->typeLabel() }}</span>

        @if ($node->permission_name)
            <code class="text-xs font-mono text-gray-400 dark:text-gray-500">{{ $node->permission_name }}</code>
        @endif

        @unless ($node->status)
            <span class="text-[11px] text-gray-400 dark:text-gray-500">（已停用）</span>
        @endunless

        @if ($childIds)
            <label class="ml-auto inline-flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 cursor-pointer">
                <input
                    type="checkbox"
                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    onchange="togglePermissionGroup(this, {{ json_encode(array_values($childIds)) }})"
                >
                全选本组
            </label>
        @endif
    </div>
</div>

@if ($node->children->isNotEmpty())
    <div class="mt-2 space-y-2">
        @foreach ($node->children as $child)
            @include('roles.partials.permission-tree', [
                'node' => $child,
                'depth' => $depth + 1,
                'permissionIds' => $permissionIds,
                'rolePermissionIds' => $rolePermissionIds ?? [],
                'groupPermissionIds' => $groupPermissionIds,
            ])
        @endforeach
    </div>
@endif
