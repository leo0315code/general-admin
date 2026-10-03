<?php

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * CORS 策略固化测试。
 *
 * 本项目是纯 SSR 后台，默认不对外提供跨域接口。这里把「默认收紧」
 * 这个决策写成断言，防止以后有人为了省事把 allowed_origins 改成 '*'。
 */
class CorsTest extends TestCase
{
    protected function tearDown(): void
    {
        // 还原默认（空白名单），避免污染其他用例
        Config::set('cors.allowed_origins', []);

        parent::tearDown();
    }

    public function test_default_policy_allow_no_cross_origin(): void
    {
        Config::set('cors.allowed_origins', []);

        $this->call('GET', '/health', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example.com'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_whitelisted_origin_is_allowed(): void
    {
        Config::set('cors.allowed_origins', [
            'https://ops.example.com',
            'https://ops2.example.com',
        ]);

        $this->call('GET', '/health', [], [], [], ['HTTP_ORIGIN' => 'https://ops2.example.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://ops2.example.com');
    }

    /**
     * 注意 fruitcake/php-cors 的一个实现细节：白名单只有一条时会固定回填该条
     * （isSingleOriginAllowed 分支），不校验请求里的 Origin。这本身是安全的
     * ——浏览器仍会拿 ACAO 与真实 origin 比对——因此这里断言的是「回填值不等于
     * 攻击者的 origin」，而不是「完全没有头」。
     */
    public function test_single_whitelist_entry_never_echoes_attacker_origin(): void
    {
        Config::set('cors.allowed_origins', ['https://ops.example.com']);

        $this->call('GET', '/health', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'https://ops.example.com');
    }

    /**
     * 白名单两条及以上时走动态分支：只给名单内的 origin 回 ACAO，
     * 名单外完全不带该头（浏览器因此会拦截响应）。
     */
    public function test_origin_not_in_whitelist_is_rejected(): void
    {
        Config::set('cors.allowed_origins', [
            'https://ops.example.com',
            'https://ops2.example.com',
        ]);

        $this->call('GET', '/health', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example.com'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_only_listed_paths_are_cors_enabled(): void
    {
        Config::set('cors.allowed_origins', ['https://ops.example.com']);

        // 后台页面不属于 paths 白名单，即便来源合法也不应加跨域头
        $this->call('GET', '/console/login', [], [], [], ['HTTP_ORIGIN' => 'https://ops.example.com'])
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_credentials_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('cors.supports_credentials'));
    }
}
