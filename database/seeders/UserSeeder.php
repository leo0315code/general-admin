<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 用户种子数据（基于 spatie/laravel-permission）
 *
 * 内置账号：
 * - admin@example.com / password（超级管理员）
 * - editor@example.com / password（编辑）
 * 另生成若干测试用户并随机分配角色。
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 超级管理员
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => '管理员',
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

        // 测试用户：随机分配 admin / editor 角色（少量 admin，其余 editor）
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
