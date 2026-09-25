<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Spatie\Permission\Models\Role;

/**
 * 用户批量导入
 *
 * 模板列：姓名 / 邮箱 / 密码 / 角色
 * - 姓名、邮箱必填；姓名与邮箱均唯一（含软删除记录），重复整行跳过并收集错误
 * - 密码选填：留空则生成随机强密码（不再使用固定弱密码 123456）
 * - 角色选填：为空默认 editor，须为已存在的角色标识，否则整行跳过
 * - 导入过程中收集错误行，控制器统一提示；单行失败不影响其它行
 */
class UsersImport implements SkipsEmptyRows, ToModel, WithChunkReading, WithHeadingRow
{
    /** 成功导入数量 */
    public static int $created = 0;

    /** 错误行信息列表 */
    public static array $errors = [];

    /** 分块读取大小（降低大文件内存占用，每块自动事务） */
    public function chunkSize(): int
    {
        return 500;
    }

    /** 重置统计（每次导入前调用） */
    public static function reset(): void
    {
        self::$created = 0;
        self::$errors = [];
    }

    public function __construct()
    {
        // 中文表头必须保留原样：默认 HeadingRowFormatter 是 slug（Str::slug 会把中文转成空串，
        // 导致所有列头变成空字符串、行内取不到任何字段）。这是既有隐藏 bug，由导入回归测试暴露。
        HeadingRowFormatter::default('none');
    }

    public function model(array $row): Model|array|null
    {
        $name = trim((string) ($row['姓名'] ?? ''));
        $email = trim((string) ($row['邮箱'] ?? ''));

        // 基础校验
        if ($name === '' || $email === '') {
            self::$errors[] = '行缺失姓名或邮箱：'.json_encode($row, JSON_UNESCAPED_UNICODE);

            return null;
        }

        // 邮箱唯一（含软删除记录：软删行的 email/name 仍占用唯一索引）
        if (User::query()->withTrashed()->where('email', $email)->exists()) {
            self::$errors[] = "邮箱 {$email} 已存在，已跳过";

            return null;
        }

        // 姓名唯一（含软删除记录）—— 缺此校验会撞 users.name 唯一索引导致整批 500
        if (User::query()->withTrashed()->where('name', $name)->exists()) {
            self::$errors[] = "姓名 {$name} 已存在，已跳过";

            return null;
        }

        // 密码：留空生成随机强密码；填写则须 >= 8 位
        $password = trim((string) ($row['密码'] ?? ''));
        if ($password === '') {
            $password = Str::password(16);
        } elseif (mb_strlen($password) < 8) {
            self::$errors[] = "用户 {$name}（{$email}）密码少于 8 位，已跳过";

            return null;
        }

        // 角色：须为已存在的角色标识，否则整行跳过（避免 syncRoles 抛异常中断整批）
        $roleName = trim((string) ($row['角色'] ?? '')) ?: User::ROLE_EDITOR;
        if (! Role::query()->where('name', $roleName)->exists()) {
            self::$errors[] = "用户 {$name}（{$email}）指定角色「{$roleName}」不存在，已跳过";

            return null;
        }

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
