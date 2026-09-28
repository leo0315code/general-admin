<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 安全响应头回归（UI 现代化重构 · T05 / SEC-4）
 *
 * 断言 web 组响应携带安全头：
 * - X-Frame-Options / X-Content-Type-Options / Referrer-Policy 恒存在；
 * - 生产环境（APP_ENV=production）追加 Strict-Transport-Security 与 Content-Security-Policy。
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_web_response_has_security_headers(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_guest_pages_also_carry_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_production_environment_adds_hsts_and_csp(): void
    {
        // 模拟生产环境（detectEnvironment 会重写 Application 的 environment 属性）
        $this->app->detectEnvironment(fn () => 'production');

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('default-src', $csp);
        // 脚本不再放行 unsafe-inline，改为每请求 nonce
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[^']+'/", $csp);
        // 样式保留 unsafe-inline（Vue :style 绑定无法用 nonce）
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);

        // 恢复环境，避免影响后续测试
        $this->app->detectEnvironment(fn () => 'testing');
    }

    /**
     * 生产 CSP 收紧后，任何漏加 nonce 的内联脚本都会被浏览器拦截（页面白屏）。
     * 这里扫描关键页面的响应体，保证「内联脚本必然带 nonce」这一不变量。
     */
    public function test_all_inline_scripts_carry_csp_nonce(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        // expectInline：该页面是否应存在内联脚本（登录页为纯 Vue 挂载，无内联脚本）
        $pages = [
            'dashboard（已登录）' => [
                'render' => fn () => $this->actingAs($admin)->get(route('dashboard')),
                'expectInline' => true,
            ],
            '登录页' => [
                'render' => fn () => $this->get(route('login')),
                'expectInline' => false,
            ],
            // 错误页直接渲染视图（走 HTTP 会被 testing 环境的异常页替换，测不到真实模板）
            '404 错误页' => [
                'render' => fn () => view('errors.404')->render(),
                'expectInline' => true,
            ],
            '500 错误页' => [
                'render' => fn () => view('errors.500')->render(),
                'expectInline' => true,
            ],
        ];

        foreach ($pages as $label => $page) {
            $render = $page['render'];
            $result = $render();
            $html = is_string($result) ? $result : (string) $result->getContent();

            preg_match_all('/<script\b[^>]*>/i', $html, $matches);

            $inline = 0;
            foreach ($matches[0] as $tag) {
                // 外部脚本（有 src）由 'self' 放行，不需要 nonce
                if (str_contains($tag, 'src=')) {
                    continue;
                }

                $inline++;
                $this->assertMatchesRegularExpression(
                    '/nonce=/i',
                    $tag,
                    "{$label} 存在未加 nonce 的内联脚本，生产环境会被 CSP 拦截：{$tag}"
                );
            }

            if ($page['expectInline']) {
                $this->assertGreaterThan(0, $inline, "{$label} 未渲染内联脚本，请确认模板是否改动");
            } else {
                $this->assertSame(0, $inline, "{$label} 出现内联脚本，请补 nonce 并更新本用例预期");
            }
        }
    }
}
