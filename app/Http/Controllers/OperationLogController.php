<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 操作日志控制器：登录审计 + 操作审计列表（分页 + 多条件筛选）
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
            ->latest('id')
            ->paginate(config('app.pagination', self::PER_PAGE))
            ->withQueryString();

        // 操作类型筛选选项（去重）
        $actionOptions = OperationLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return view('logs.index', compact('logs', 'keyword', 'action', 'date', 'actionOptions'));
    }
}
