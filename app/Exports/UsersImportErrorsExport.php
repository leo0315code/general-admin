<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * 用户导入失败明细导出
 *
 * 导入时未成功的行（姓名/邮箱重复、密码过短、角色不存在、缺失必填列）
 * 可直接下载成 xlsx，修正后重传，避免逐条手抄屏幕上的提示文本。
 *
 * 数据由 UserController 暂存在缓存（10 分钟）后经 token 取回，
 * 不在 session / URL 中携带明细，避免大数据量撑爆 flash 数据。
 */
class UsersImportErrorsExport implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param  list<array{name: string, email: string, role: string, reason: string}>  $rows */
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['姓名', '邮箱', '角色', '失败原因'];
    }

    public function title(): string
    {
        return '导入失败明细';
    }

    public function array(): array
    {
        return array_map(
            fn (array $row): array => [$row['name'], $row['email'], $row['role'], $row['reason']],
            $this->rows
        );
    }
}
