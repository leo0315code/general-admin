<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * 输出登录验证码 SVG 图片（并重置 session 验证码）。
     * 本地调试（APP_DEBUG=true）时通过响应头返回验证码，便于自动化冒烟。
     */
    public function captcha()
    {
        $svg = Captcha::svg();

        $response = response($svg)->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');

        if (config('app.debug')) {
            $response->header('X-Captcha-Debug', session()->get(Captcha::SESSION_KEY));
        }

        return $response;
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // 记录登录成功
        \App\Support\OperationLogger::log(
            $request->user()?->id,
            $request->user()?->name,
            'POST',
            '登录',
            '用户登录成功',
            '登录',
            $request->ip(),
            substr((string) $request->userAgent(), 0, 500)
        );

        // 仅信任属于当前后台前缀的 intended URL（防止旧会话遗留的旧路径/外部 URL 导致 404），
        // 其余情况一律回到仪表盘。
        $intended = $request->session()->pull('url.intended');
        $intendedPath = $intended ? (parse_url((string) $intended, PHP_URL_PATH) ?: $intended) : null;

        if ($intendedPath && str_starts_with((string) $intendedPath, '/'.config('app.admin_prefix'))) {
            return redirect($intended);
        }

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // 记录退出登录（在 logout 前读取用户信息）
        if ($user) {
            \App\Support\OperationLogger::log(
                $user->id,
                $user->name,
                'POST',
                '退出',
                '用户退出登录',
                '登录',
                $request->ip(),
                substr((string) $request->userAgent(), 0, 500)
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
