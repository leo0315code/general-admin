<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 账号生命周期回归：启停、登录痕迹、首登强制改密
 */
class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    /** 以指定凭据发起登录（带一次性验证码） */
    private function loginAs(string $login, string $password = 'password')
    {
        $this->app['auth']->forgetGuards();

        $captcha = Captcha::generate();

        return $this->post(route('login'), [
            'username' => $login,
            'password' => $password,
            'captcha' => $captcha,
        ]);
    }

    // ---- 账号启停 ----

    public function test_disabled_user_cannot_login(): void
    {
        $user = User::query()->where('email', 'user5@example.com')->firstOrFail();
        $user->update(['status' => User::STATUS_DISABLED]);

        $this->loginAs('user5@example.com')
            ->assertSessionHasErrors('username');

        // 记录停用登录失败审计
        $this->assertDatabaseHas('operation_logs', [
            'action' => '登录失败',
            'description' => '账号已停用',
        ]);
    }

    public function test_toggle_status_disables_and_reenables(): void
    {
        $user = User::query()->where('email', 'user8@example.com')->firstOrFail();

        $this->actingAs($this->admin())
            ->patch(route('users.toggle-status', $user))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 0]);

        $this->actingAs($this->admin())
            ->patch(route('users.toggle-status', $user))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 1]);
    }

    public function test_cannot_disable_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('users.toggle-status', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'status' => 1]);
    }

    // ---- 登录痕迹 ----

    public function test_login_records_last_login_at_and_ip(): void
    {
        $this->loginAs('user6@example.com')
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'user6@example.com')->firstOrFail();

        $this->assertNotNull($user->last_login_at);
        $this->assertSame('127.0.0.1', $user->last_login_ip);
    }

    // ---- 首登强制改密 ----

    public function test_reset_password_sets_must_change_flag(): void
    {
        $user = User::query()->where('email', 'user9@example.com')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('users.reset-password', $user), [
                'new_password' => 'newpass123',
                'new_password_confirmation' => 'newpass123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'must_change_password' => 1]);
    }

    public function test_must_change_password_flow_redirects_then_clears(): void
    {
        $user = User::query()->where('email', 'user10@example.com')->firstOrFail();
        $user->update(['must_change_password' => true]);

        // 登录成功但被强制跳转改密页
        $this->loginAs('user10@example.com')
            ->assertRedirect(route('password.setup'));

        // 改密页可访问，展示中文提示
        $this->app['auth']->forgetGuards();
        $this->actingAs($user->fresh())
            ->get(route('password.setup'))
            ->assertOk()
            ->assertSee('首次登录，请设置新密码');

        // 提交新密码 → 清除标志并进入后台
        $this->actingAs($user->fresh())
            ->post(route('password.setup.update'), [
                'password' => 'brandnew123',
                'password_confirmation' => 'brandnew123',
            ])
            ->assertRedirect(route('dashboard'));

        $fresh = $user->fresh();
        $this->assertFalse((bool) $fresh->must_change_password);
        $this->assertTrue(Hash::check('brandnew123', $fresh->password));

        // 之后访问后台不再被拦截
        $this->actingAs($fresh)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_guest_cannot_access_password_setup(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('password.setup'))->assertRedirect(route('login'));
    }
}
