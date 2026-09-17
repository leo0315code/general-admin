<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Support\ListQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
                    $query->where('username', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhere('ip', 'like', "%{$keyword}%");
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
}
