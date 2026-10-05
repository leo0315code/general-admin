<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 账号安全回归：改密必须让旧会话失效 + 登录设备可看可踢。
 *
 * 为什么要在测试里显式切 session.driver：
 * phpunit.xml 钉的是 array 驱动（避免测试依赖外部状态），而 sessions 表在
 * array 驱动下根本不承载会话——Sessions 类会静默退化为 no-op（这是刻意的，
 * 见类注释）。所以每个用例都先 Config::set 到 database，再手工插 sessions 行。
 */
class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('session.driver', 'database');
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    /** 造一条属于某用户的会话行；返回会话 id */
    private function seedSession(int $userId, string $id, string $ip = '1.2.3.4'): string
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        return $id;
    }

    private function sessionCount(int $userId): int
    {
        return DB::table('sessions')->where('user_id', $userId)->count();
    }

    /**
     * 造一条「当前会话」行：id 必须等于真实会话 id，
     * 否则控制器里的「保留当前」比较永远不成立，用例会假失败。
     *
     * 还要把该 id 写进请求 cookie：HTTP kernel 的 StartSession 是从 cookie 取
     * 会话 id 的，不带 cookie 的话每次请求都会另起一个随机 id。
     */
    private function seedCurrentSession(int $userId, string $ip = '127.0.0.1'): string
    {
        $this->startSession();
        $id = $this->app['session']->getId();

        $this->withCookie((string) config('session.cookie'), $id);

        return $this->seedSession($userId, $id, $ip);
    }

    public function test_password_update_kicks_other_sessions_but_keeps_current(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $current = $this->seedCurrentSession($user->id);
        $this->seedSession($user->id, 'other-session-id');

        $this->assertSame(2, $this->sessionCount($user->id));

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasNoErrors();

        // 当前会话保留；其它会话被踢
        $this->assertTrue(DB::table('sessions')->where('id', $current)->exists(), '当前会话不应被踢掉');
        $this->assertSame(1, $this->sessionCount($user->id));
    }

    public function test_forced_password_setup_kicks_other_sessions(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user);
        $current = $this->seedCurrentSession($user->id);
        $this->seedSession($user->id, 'other-session-id');

        $this->actingAs($user)
            ->post(route('password.setup.update'), [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(DB::table('sessions')->where('id', $current)->exists());
        $this->assertSame(1, $this->sessionCount($user->id));
    }

    /** 管理员重置他人密码：被重置者的全部会话都要踢掉（他本人不在线上） */
    public function test_admin_reset_password_kicks_all_sessions_of_target_user(): void
    {
        $target = User::factory()->create();
        $this->seedSession($target->id, 'target-session-1');
        $this->seedSession($target->id, 'target-session-2');

        $this->actingAs($this->admin())
            ->post(route('users.reset-password', $target), [
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->sessionCount($target->id), '重置密码后目标用户的会话应全部失效');
        $this->assertTrue($target->refresh()->must_change_password);
    }

    public function test_device_list_is_rendered_on_profile_page(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user->id, 'session-a', '10.0.0.1');

        // 卡片由 Vue 渲染，Blade 只输出 props；故断言落在 props 里的解析结果上
        // （「登录设备」是 Vue 模板文案，服务端 HTML 里没有）
        $html = (string) $this->actingAs($user)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringContainsString('10.0.0.1', $html);
        $this->assertStringContainsString('Chrome', $html, '应解析出浏览器');
        $this->assertStringContainsString('Windows', $html, '应解析出平台');
    }

    public function test_can_kick_a_single_session(): void
    {
        $user = User::factory()->create();
        $this->seedSession($user->id, 'keep-me');
        $this->seedSession($user->id, 'kick-me');

        $this->actingAs($user)
            ->delete(route('profile.sessions.destroy', ['session' => 'kick-me']))
            ->assertSessionHasNoErrors();

        $this->assertFalse(DB::table('sessions')->where('id', 'kick-me')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'keep-me')->exists());
    }

    /** 踢当前会话会被拒绝：否则操作者自己被登出，体验突兀 */
    public function test_cannot_kick_the_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $current = $this->seedCurrentSession($user->id);

        $this->actingAs($user)
            ->delete(route('profile.sessions.destroy', ['session' => $current]))
            ->assertSessionHas('error');

        $this->assertTrue(DB::table('sessions')->where('id', $current)->exists());
    }

    public function test_kick_others_keeps_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $current = $this->seedCurrentSession($user->id);
        $this->seedSession($user->id, 'other-1');
        $this->seedSession($user->id, 'other-2');

        $this->actingAs($user)->delete(route('profile.sessions.destroy-others'));

        $this->assertSame(1, $this->sessionCount($user->id));
        $this->assertTrue(DB::table('sessions')->where('id', $current)->exists());
    }

    /** 非 database 驱动时 sessions 表不承载会话，必须静默 no-op 而不是误删 */
    public function test_session_kick_is_noop_when_driver_is_not_database(): void
    {
        Config::set('session.driver', 'array');

        $user = User::factory()->create();
        $this->seedSession($user->id, 'stale-row');

        $this->actingAs($user)->delete(route('profile.sessions.destroy-others'));

        $this->assertTrue(DB::table('sessions')->where('id', 'stale-row')->exists(), '非 database 驱动下不应动 sessions 表');
    }

    public function test_reset_password_actually_rejects_old_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'brandnewpass1',
                'password_confirmation' => 'brandnewpass1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brandnewpass1', $user->refresh()->password));
    }
}
