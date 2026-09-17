<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 首次登录强制改密：must_change_password=1 的用户访问后台页面一律跳转改密页。
 * 放行自身路由（password.setup）与退出登录（由 auth.php 单独分组，不经过本中间件）。
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            return redirect()->route('password.setup');
        }

        return $next($request);
    }
}
