<?php

namespace App\Exports;

use App\Models\OperationLog;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * 操作日志导出（审计留证）
 *
 * 与列表页 index 同源筛选（关键词 / 操作类型 / 日期），FromQuery + chunk
 * 流式读取，日志表再大也不整表加载进内存。
 */
class LogsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        protected string $keyword = '',
        protected ?string $action = null,
        protected ?string $date = null,
    ) {
        //
    }

    public function headings(): array
    {
        return ['ID', '时间', '用户名', '操作类型', '模块', '请求方法', '描述', 'IP 地址', 'User-Agent'];
    }

    public function query(): Builder
    {
        return OperationLog::query()
            ->when($this->keyword !== '', function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->whereRaw("username LIKE ? ESCAPE '!'", ['%'.escape_like($this->keyword).'%'])
                        ->orWhereRaw("description LIKE ? ESCAPE '!'", ['%'.escape_like($this->keyword).'%'])
                        ->orWhereRaw("ip LIKE ? ESCAPE '!'", ['%'.escape_like($this->keyword).'%']);
                });
            })
            ->when($this->action, fn (Builder $query) => $query->where('action', $this->action))
            ->when($this->date, fn (Builder $query) => $query->whereDate('created_at', $this->date))
            ->latest('id');
    }

    public function map($log): array
    {
        return [
            $log->id,
            $log->created_at?->format('Y-m-d H:i:s'),
            $log->username,
            $log->action,
            $log->module,
            $log->method,
            $log->description,
            $log->ip,
            $log->user_agent,
        ];
    }
}
