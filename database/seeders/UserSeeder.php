<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 用户种子数据（基于 spatie/laravel-permission）
 *
 * 内置账号：
 * - leo0315 / admin@example.com / password（超级管理员）
 * - editor@example.com / password（编辑）
 * - 另生成 12 个测试用户：user1 为 admin 角色，user2 ~ user12 为 editor 角色
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 超级管理员
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'leo0315',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        // 编辑
        $editor = User::query()->updateOrCreate(
            ['email' => 'editor@example.com'],
            [
                'name' => '编辑',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        $editor->syncRoles(['editor']);

        // 测试用户：user1 授予 admin 角色用于验证多管理员场景，其余为 editor
        foreach (range(1, 12) as $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name' => "测试用户{$i}",
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles($i === 1 ? ['admin'] : ['editor']);
        }
    }
}
