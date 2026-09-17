<?php

namespace App\Http\Middleware;

use App\Support\OperationLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 操作日志中间件：自动记录已登录用户的后台操作。
 * - 写操作（POST/PUT/PATCH/DELETE）全部记录；
 * - GET 仅记录导出类请求（路由名以 .export 结尾，如 users.export / posts.export）；
 * - 业务异常时也记录一笔「执行失败」，便于审计问题操作；
 * - 日志写入自身失败不会影响业务主流程（吞掉日志异常）。
 * 登录/登出/验证码由控制器与 LoginRequest 手动记录，避免重复。
 */
class LogOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);

            $this->record($request);

            return $response;
        } catch (\Throwable $e) {
            // 业务抛异常也要留痕；记录后继续抛出原异常，不吞错
            $this->record($request, failed: true);

            throw $e;
        }
    }

    protected function record(Request $request, bool $failed = false): void
    {
        // 写操作一律记录；GET 仅记录导出类请求（避免普通浏览产生日志噪音）
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $routeName = $request->route()?->getName();

            if (! $routeName || ! str_ends_with($routeName, '.export')) {
                return;
            }
        }

        // 仅记录已登录用户
        $user = $request->user();
        if (! $user) {
            return;
        }

        // 登录/登出/验证码由专门逻辑记录
        $routeName = $request->route()?->getName();
        if (in_array($routeName, ['login', 'logout', 'captcha'], true)) {
            return;
        }

        try {
            [$module, $action, $description] = OperationLogger::resolve($request);

            if ($failed) {
                $description .= '（执行失败）';
            }

            OperationLogger::log(
                $user->id,
                $user->name,
                $request->method(),
                $action,
                $description,
                $module,
                $request->ip(),
                substr((string) $request->userAgent(), 0, 500)
            );
        } catch (\Throwable) {
            // 审计失败不应影响业务主流程（如日志表写入异常时静默跳过）
        }
    }
}
