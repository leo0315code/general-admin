<?php

namespace App\Http\Middleware;

use App\Support\OperationLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 操作日志中间件：自动记录已登录用户的后台写操作（POST/PUT/PATCH/DELETE）。
 * 登录/登出/验证码由控制器与 LoginRequest 手动记录，避免重复。
 */
class LogOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->record($request);

        return $response;
    }

    protected function record(Request $request): void
    {
        // 仅记录写操作
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return;
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

        [$module, $action, $description] = OperationLogger::resolve($request);

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
    }
}
