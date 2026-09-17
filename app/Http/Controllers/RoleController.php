<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 角色管理控制器（基于 spatie/laravel-permission）
 *
 * CRUD + 权限分配（checkbox 多选）；内置 admin 角色不可删除、标识不可修改。
 * 权限的中文展示名使用 permissions.label（name 作为权限标识）。
 */
class RoleController extends Controller
{
    /** 角色列表：含权限数量与用户数量 */
    public function index(): View
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('id')
            ->paginate(config('app.pagination', 15));

        return view('roles.index', compact('roles'));
    }

    /** 创建角色表单（带权限树复选框） */
    public function create(): View
    {
        return view('roles.create', $this->formData());
    }

    /** 保存角色并分配权限 */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()
            ->route('roles.index')
            ->with('success', "角色「{$role->name}」创建成功。");
    }

    /** 编辑角色表单（带权限树复选框） */
    public function edit(Role $role): View
    {
        return view('roles.edit', array_merge($this->formData(), [
            'role' => $role,
            'rolePermissionIds' => $role->permissions()->pluck('id')->all(),
        ]));
    }

    /** 更新角色与权限分配 */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        // admin 角色标识不允许修改，防止绕过超级管理员判定
        if ($role->name === User::ROLE_ADMIN && $request->validated('name') !== User::ROLE_ADMIN) {
            return redirect()
                ->route('roles.edit', $role)
                ->with('error', '内置 admin 角色的标识不允许修改。');
        }

        $role->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()
            ->route('roles.edit', $role)
            ->with('success', "角色「{$role->name}」更新成功。");
    }

    /** 删除角色（内置 admin 角色与已分配用户的角色不允许删除） */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === User::ROLE_ADMIN) {
            return redirect()
                ->route('roles.index')
                ->with('error', '内置 admin 角色不允许删除。');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('roles.index')
                ->with('error', "角色「{$role->name}」已分配给用户，请先解除分配再删除。");
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', "角色「{$role->name}」已删除。");
    }

    /**
     * 授权表单公共数据：菜单树 + 权限标识映射 + 分组权限 ID
     *
     * 权限来自 menus 表（菜单即权限），因此授权页与「菜单管理」保持同源。
     *
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        $permissionIds = Permission::query()->pluck('id', 'name')->all();

        return [
            'menuTree' => Menu::tree(),
            'permissionIds' => $permissionIds,
            'groupPermissionIds' => $this->groupPermissionIds($permissionIds),
        ];
    }

    /**
     * 每个节点（含其全部后代）的权限 ID 集合，用于「全选本组」。
     *
     * @param  array<string, int>  $permissionIds  权限标识 => 权限 ID
     * @return array<int, list<int>>
     */
    protected function groupPermissionIds(array $permissionIds): array
    {
        $map = [];

        // flatten() 为深度优先（父在前），逆序即可先算子节点
        foreach (array_reverse(Menu::flatten()) as $row) {
            $ids = [];

            foreach ($row['menu']->children as $child) {
                $ids = array_merge($ids, $map[$child->id] ?? []);
            }

            if ($row['menu']->permission_name && isset($permissionIds[$row['menu']->permission_name])) {
                $ids[] = $permissionIds[$row['menu']->permission_name];
            }

            $map[$row['menu']->id] = array_values(array_unique($ids));
        }

        return $map;
    }
}
