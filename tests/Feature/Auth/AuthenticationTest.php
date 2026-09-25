<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_username(): void
    {
        $user = User::factory()->create(['name' => '测试用户甲']);

        session(['login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => '测试用户甲',
            'password' => 'password',
            'captcha' => '1234',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_using_email(): void
    {
        $user = User::factory()->create();

        session(['login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => $user->email,
            'password' => 'password',
            'captcha' => '1234',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        session(['login_captcha' => '1234']);

        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'wrong-password',
            'captcha' => '1234',
        ]);

        $this->assertGuest();
    }

    public function test_login_rejects_wrong_captcha(): void
    {
        $user = User::factory()->create(['name' => '验证码用户']);

        session(['login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => '验证码用户',
            'password' => 'password',
            'captcha' => '9999',
        ]);

        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_captcha_is_consumed_after_verification(): void
    {
        $user = User::factory()->create(['name' => '一次性验证码用户']);

        session(['login_captcha' => '1234']);

        // 第一次正确验证码校验失败于密码（验证码被消费）
        $this->post(route('login'), [
            'username' => '一次性验证码用户',
            'password' => 'wrong-password',
            'captcha' => '1234',
        ]);

        // 第二次即使密码正确，验证码也已被消费 → 报验证码错误
        $response = $this->post(route('login'), [
            'username' => '一次性验证码用户',
            'password' => 'password',
            'captcha' => '1234',
        ]);

        $response->assertSessionHasErrors('captcha');
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_is_rate_limited_per_ip_not_per_username(): void
    {
        // 同一 IP 连续 5 次密码错误（验证码正确）后触发限流
        RateLimiter::clear('127.0.0.1');

        foreach (['测试用户甲', '测试用户乙', '测试用户丙', '测试用户丁', '测试用户戊'] as $i => $name) {
            User::factory()->create(['name' => $name]);

            session(['login_captcha' => '1234']);

            $this->post(route('login'), [
                'username' => $name,
                'password' => 'wrong-password',
                'captcha' => '1234',
            ]);
        }

        // 第 6 次换全新用户名 + 正确密码，仍被限流 → 证明无法通过切换用户名绕过
        session(['login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => '全新用户名',
            'password' => 'wrong-password',
            'captcha' => '1234',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_captcha_failure_also_consumes_rate_limit(): void
    {
        RateLimiter::clear('127.0.0.1');

        // 验证码错误同样计次：5 次后第 6 次即使验证码正确也被限流
        for ($i = 0; $i < 5; $i++) {
            session(['login_captcha' => '1234']);

            $this->post(route('login'), [
                'username' => '验证码爆破用户',
                'password' => 'wrong-password',
                'captcha' => '9999',
            ]);
        }

        session(['login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => '验证码爆破用户',
            'password' => 'wrong-password',
            'captcha' => '1234',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_ignores_stale_intended_url_outside_admin_prefix(): void
    {
        // 模拟旧会话遗留的 intended（如旧版本无前缀的 /dashboard），登录后应回到当前后台仪表盘
        $user = User::factory()->create(['name' => '旧路径用户']);

        session(['url.intended' => '/dashboard', 'login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => '旧路径用户',
            'password' => 'password',
            'captcha' => '1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_login_redirects_to_intended_url_within_admin_prefix(): void
    {
        $user = User::factory()->create(['name' => '合法路径用户']);

        session(['url.intended' => '/'.config('app.admin_prefix').'/users', 'login_captcha' => '1234']);

        $response = $this->post(route('login'), [
            'username' => '合法路径用户',
            'password' => 'password',
            'captcha' => '1234',
        ]);

        $response->assertRedirect('/'.config('app.admin_prefix').'/users');
    }
}
