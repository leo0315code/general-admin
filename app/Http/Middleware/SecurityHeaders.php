<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
 *   - Content-Security-Policy：script-src 使用**每请求随机 nonce**（不再放行 'unsafe-inline'），
 *     所有内联脚本（主题防闪烁、Vue 数据注入）必须带 nonce="{{ csp_nonce() }}"，
 *     否则将被浏览器拦截导致页面白屏——新增内联脚本时务必补 nonce。
 *     style-src 仍保留 'unsafe-inline'：Vue 的 :style 绑定是元素 style 属性，
 *     CSP 没有针对属性的 nonce 机制，收紧会使动态样式全部失效。
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
            $response->headers->set('Content-Security-Policy', self::contentSecurityPolicy());
        }
    }

    /**
     * 生产环境 CSP：脚本走 nonce（每请求随机），样式因 Vue:style 绑定保留 unsafe-inline。
     */
    public static function contentSecurityPolicy(): string
    {
        return sprintf(
            "default-src 'self'; script-src 'self' 'nonce-%s'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'",
            Vite::cspNonce()
        );
    }
}
