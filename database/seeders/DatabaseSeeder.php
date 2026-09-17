<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 总种子：菜单权限节点 → 角色权限 → 用户 → 文章 → 字典 → 系统设置
 *
 * 顺序有依赖，勿随意调整：
 * 1. MenuPermissionSeeder 必须最先执行——权限由菜单树生成（菜单即权限）；
 * 2. RolePermissionSeeder 依赖第 1 步产生的权限记录；
 * 3. PostSeeder 依赖 UserSeeder 产生的作者；UserSeeder 依赖角色。
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MenuPermissionSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
            PostSeeder::class,
            DictSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
