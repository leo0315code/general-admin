<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * 用户批量导入
 *
 * 模板列：姓名 / 邮箱 / 密码 / 角色
 * - 密码选填，为空默认 123456
 * - 角色选填，为空默认 editor（须为已存在的角色标识）
 * - 导入过程中收集错误行，控制器统一提示
 */
class UsersImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    /** 成功导入数量 */
    public static int $created = 0;

    /** 错误行信息列表 */
    public static array $errors = [];

    /** 重置统计（每次导入前调用） */
    public static function reset(): void
    {
        self::$created = 0;
        self::$errors = [];
    }

    public function model(array $row)
    {
        $name = trim((string) ($row['姓名'] ?? ''));
        $email = trim((string) ($row['邮箱'] ?? ''));

        // 基础校验
        if ($name === '' || $email === '') {
            self::$errors[] = "行缺失姓名或邮箱：".json_encode($row, JSON_UNESCAPED_UNICODE);

            return null;
        }

        if (User::query()->where('email', $email)->exists()) {
            self::$errors[] = "邮箱 {$email} 已存在，已跳过";

            return null;
        }

        $password = trim((string) ($row['密码'] ?? '')) ?: '123456';
        $roleName = trim((string) ($row['角色'] ?? '')) ?: User::ROLE_EDITOR;

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$roleName]);

        self::$created++;

        return $user;
    }
}
