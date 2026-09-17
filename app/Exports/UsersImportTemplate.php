<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * 用户导入模板（含表头与示例行）
 */
class UsersImportTemplate implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['姓名', '邮箱', '密码', '角色'];
    }

    public function array(): array
    {
        return [
            ['张三', 'zhangsan@example.com', '123456', 'editor'],
            ['李四', 'lisi@example.com', '', 'admin'],
        ];
    }
}
