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

        $this->assertStringContainsString('default-src', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", (string) $response->headers->get('Content-Security-Policy'));

        // 恢复环境，避免影响后续测试
        $this->app->detectEnvironment(fn () => 'testing');
    }
}
