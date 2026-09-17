<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 角色种子（基于 spatie/laravel-permission）
 *
 * 注意：权限清单不在本文件维护——本项目「菜单即权限」，
 * 权限由 MenuPermissionSeeder 依据菜单树自动写入，本文件只负责角色与授权。
 *
 * 内置角色：
 * - admin   超级管理员（拥有全部权限；AuthServiceProvider 中 Gate::before 短路放行）
 * - editor  编辑（仪表盘 + 文章管理，含文章按钮权限）
 */
class RolePermissionSeeder extends Seeder
{
    /** editor 角色的权限清单 */
    protected const EDITOR_PERMISSIONS = [
        'dashboard.view',
        'post.manage',
        'posts.create',
        'posts.update',
        'posts.destroy',
    ];

    public function run(): void
    {
        // 1. 内置角色
        $adminRole = Role::findOrCreate('admin');
        $adminRole->update(['description' => '拥有系统全部权限']);

        $editorRole = Role::findOrCreate('editor');
        $editorRole->update(['description' => '可管理文章内容']);

        // 2. 角色-权限分配（权限来自菜单树，见 MenuPermissionSeeder）
        $adminRole->syncPermissions(Permission::all());
        $editorRole->syncPermissions(
            Permission::query()->whereIn('name', self::EDITOR_PERMISSIONS)->get()
        );
    }
}
