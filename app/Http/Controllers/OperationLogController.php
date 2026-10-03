<?php

namespace App\Http\Controllers;

use App\Exports\LogsExport;
use App\Models\OperationLog;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 操作日志控制器：登录审计 + 操作审计列表（分页 + 多条件筛选 + 每页条数/排序）
 */
class OperationLogController extends Controller
{
    /** 每页显示数量 */
    protected const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('search'));
        $action = $request->query('action');
        $date = $request->query('date');

        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'action', 'ip', 'created_at']
        );

        $logs = OperationLog::query()
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->whereRaw("username LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%'])
                        ->orWhereRaw("description LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%'])
                        ->orWhereRaw("ip LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%']);
                });
            })
            ->when($action, fn ($query, $action) => $query->where('action', $action))
            ->when($date, fn ($query, $date) => $query->whereDate('created_at', $date))
            ->when(
                $sort,
                fn ($query) => $query->orderBy($sort, $dir),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        // 操作类型筛选选项（去重）
        $actionOptions = OperationLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return view('logs.index', compact('logs', 'keyword', 'action', 'date', 'actionOptions', 'sort', 'dir'));
    }

    /** 日志详情（展示 User-Agent 等列表页未展示的审计字段） */
    public function show(OperationLog $log): View
    {
        return view('logs.show', compact('log'));
    }

    /** 导出操作日志（Excel，跟随当前筛选条件，FromQuery 流式不整表加载） */
    public function export(Request $request)
    {
        Gate::authorize('log.export');

        $keyword = trim((string) $request->query('search'));
        $action = $request->query('action');
        $date = $request->query('date');

        return Excel::download(
            new LogsExport($keyword, $action, $date),
            '操作日志-'.date('YmdHis').'.xlsx'
        );
    }
}
