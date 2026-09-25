<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全响应头中间件（UI 现代化重构 · T05 / SEC-4）
 *
 * 挂载到 web 组，为所有 Web 响应补充安全头：
 * - X-Frame-Options: SAMEORIGIN             防点击劫持（禁止 iframe 嵌入）
 * - X-Content-Type-Options: nosniff         禁止 MIME 嗅探
 * - Referrer-Policy: strict-origin-when-cross-origin  限制 Referer 泄露
 * - 生产环境（APP_ENV=production）追加：
 *   - Strict-Transport-Security（HSTS）
 *   - Content-Security-Policy（保留 'unsafe-inline' 以兼容 Alpine 内联表达式与主题防闪烁脚本）
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->applyHeaders($response);

        return $response;
    }

    protected function applyHeaders(Response $response): void
    {
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'"
            );
        }
    }
}
