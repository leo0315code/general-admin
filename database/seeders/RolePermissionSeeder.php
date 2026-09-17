<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 角色与权限种子数据（基于 spatie/laravel-permission）
 *
 * 内置权限（name 即权限标识，label 为中文展示名）：
 * - dashboard.view  查看仪表盘
 * - user.manage     用户管理
 * - post.manage     文章管理
 * - role.manage     角色管理
 *
 * 内置角色：
 * - admin   超级管理员（拥有全部权限）
 * - editor  编辑（仅仪表盘 + 文章管理）
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. 权限
        $permissions = [
            ['name' => 'dashboard.view', 'label' => '查看仪表盘', 'description' => '访问后台仪表盘与统计数据'],
            ['name' => 'user.manage', 'label' => '用户管理', 'description' => '管理用户：增删改、分配角色、重置密码'],
            ['name' => 'post.manage', 'label' => '文章管理', 'description' => '管理文章：增删改、发布/下线'],
            ['name' => 'role.manage', 'label' => '角色管理', 'description' => '管理角色与权限分配'],
            ['name' => 'log.manage', 'label' => '操作日志', 'description' => '查看登录审计与操作日志'],
            ['name' => 'dict.manage', 'label' => '数据字典', 'description' => '管理数据字典类型与字典项'],
        ];

        foreach ($permissions as $item) {
            $permission = Permission::findOrCreate($item['name']);
            $permission->update(['label' => $item['label'], 'description' => $item['description']]);
        }

        // 2. 角色
        $adminRole = Role::findOrCreate('admin');
        $adminRole->update(['description' => '拥有系统全部权限']);

        $editorRole = Role::findOrCreate('editor');
        $editorRole->update(['description' => '可管理文章内容']);

        // 3. 角色-权限 分配
        $adminRole->syncPermissions(Permission::all());
        $editorRole->syncPermissions(
            Permission::query()->whereIn('name', ['dashboard.view', 'post.manage'])->get()
        );
    }
}
