<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * 用户数据导出（含角色信息）
 *
 * 使用 FromQuery + chunk 流式读取：大表不整表加载进内存，
 * 且导出内容与列表页「当前筛选条件」保持一致（同源数据）。
 */
class UsersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected ?string $keyword = null)
    {
        //
    }

    public function headings(): array
    {
        return ['ID', '姓名', '邮箱', '角色', '注册时间'];
    }

    public function query(): Builder
    {
        return User::query()
            ->with('roles:id,name')
            ->when($this->keyword, function (Builder $query, string $keyword) {
                $query->where(function (Builder $query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->latest('id');
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->roles->pluck('name')->join('/'),
            $user->created_at?->format('Y-m-d H:i'),
        ];
    }
}
