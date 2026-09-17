<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * 用户数据导出（含角色信息）
 */
class UsersExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['ID', '姓名', '邮箱', '角色', '注册时间'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return User::query()
            ->with('roles')
            ->get()
            ->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->roles->pluck('name')->join('/'),
                $user->created_at?->format('Y-m-d H:i'),
            ]);
    }
}
