<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 总种子：角色权限 → 用户 → 文章
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            PostSeeder::class,
        ]);
    }
}
