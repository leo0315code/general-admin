<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\Broadcaster;
use App\Support\Notifier;
use App\Support\WsTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * WebSocket 推送（GatewayWorker）
 *
 * 关注三件事：
 * 1. 未启用时**必须静默降级**——推送挂了不能连累通知写入；
 * 2. 连接票据：一次性、短时效、消费即失效（防重放）；
 * 3. 顶栏铃铛交给 Vue 组件托管（不再有原生 JS 轮询残留）。
 */
class WebSocketPushTest extends TestCase
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

    public function test_broadcaster_is_disabled_by_default(): void
    {
        $this->assertFalse(Broadcaster::enabled(), '默认不启用 WS，未配置时必须静默降级');
        $this->assertFalse(Broadcaster::toUser($this->admin(), ['type' => 'test']), '未启用时推送应直接返回 false');
    }

    /** 启用但 Register 不可达：不能抛异常，返回 false（业务主流程不受影响） */
    public function test_broadcaster_fails_silently_when_register_unreachable(): void
    {
        config(['websocket.enabled' => true, 'websocket.register_address' => '127.0.0.1:1']);

        $this->assertFalse(Broadcaster::toUser($this->admin(), ['type' => 'test']));
    }

    /** 关键回归：WS 出问题时，站内通知照样要落库 */
    public function test_notification_still_persists_when_push_unavailable(): void
    {
        config(['websocket.enabled' => true, 'websocket.register_address' => '127.0.0.1:1']);

        $admin = $this->admin();

        $notification = Notifier::send($admin, Notification::TYPE_ACCOUNT_CREATED, '推送故障时的通知');

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
        $this->assertSame(1, Notification::unreadCountFor($admin->id));
    }

    public function test_ticket_can_be_consumed_once(): void
    {
        $admin = $this->admin();
        $ticket = WsTicket::issue($admin->id);

        $this->assertSame($admin->id, WsTicket::consume($ticket), '首次消费应返回用户 ID');
        $this->assertNull(WsTicket::consume($ticket), '票据必须一次性，防止重放');
    }

    public function test_ticket_rejects_malformed_value(): void
    {
        $this->assertNull(WsTicket::consume(''));
        $this->assertNull(WsTicket::consume('../../etc/passwd'));
        $this->assertNull(WsTicket::consume(str_repeat('a', 30)), '未签发的票据应无效');
    }

    public function test_ticket_endpoint_requires_auth(): void
    {
        $this->postJson(route('ws.ticket'))->assertUnauthorized();
    }

    public function test_ticket_endpoint_returns_disabled_when_ws_off(): void
    {
        config(['websocket.enabled' => false]);

        $this->actingAs($this->admin())
            ->postJson(route('ws.ticket'))
            ->assertOk()
            ->assertJsonPath('enabled', false);
    }

    public function test_ticket_endpoint_issues_one_time_ticket(): void
    {
        config(['websocket.enabled' => true, 'websocket.ticket_ttl' => 60]);

        $admin = $this->admin();

        $ticket = $this->actingAs($admin)
            ->postJson(route('ws.ticket'))
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonStructure(['url', 'ticket', 'ttl'])
            ->json('ticket');

        $this->assertSame($admin->id, WsTicket::consume((string) $ticket));
        $this->assertNull(Cache::get('ws.ticket.'.$ticket), '票据发出后应可被消费且不留残值');
    }

    /**
     * 浏览器 WS 地址默认由 APP_URL 推导：生产只开 WS_ENABLED 即可，不用再配一个域名
     *
     * 直接以受控的 env 重新求值配置文件（顶层闭包会重新执行），避免被启动时
     * 已算好的 config('websocket.public_url') 干扰。
     */
    public function test_public_url_is_derived_from_app_url(): void
    {
        $this->assertSame('wss://admin.example/ws', $this->reloadPublicUrl('https://admin.example'));
        $this->assertSame('ws://admin.example/ws', $this->reloadPublicUrl('http://admin.example'));
        // 端口必须沿用：本地 php artisan serve 场景
        $this->assertSame('ws://localhost:8000/ws', $this->reloadPublicUrl('http://localhost:8000'));
    }

    /** 显式 WS_PUBLIC_URL 优先：WS 与 HTTP 不同域名/端口时（如本地直连 2346）才用得上 */
    public function test_explicit_public_url_overrides_derivation(): void
    {
        $this->assertSame(
            'ws://127.0.0.1:2346',
            $this->reloadPublicUrl('https://admin.example', 'ws://127.0.0.1:2346'),
            '显式配置必须优先于推导结果'
        );
    }

    /**
     * 以受控 env 重新求值配置文件，返回推导出的 public_url（结束后还原现场）
     *
     * @param  string  $appUrl  APP_URL
     * @param  string|null  $publicUrl  可选的 WS_PUBLIC_URL 覆盖值
     */
    private function reloadPublicUrl(string $appUrl, ?string $publicUrl = null): string
    {
        $originApp = $_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? getenv('APP_URL') ?: null;
        $originWs = $_ENV['WS_PUBLIC_URL'] ?? $_SERVER['WS_PUBLIC_URL'] ?? getenv('WS_PUBLIC_URL') ?: null;

        // env() 底层适配器不唯一（$_ENV / $_SERVER / getenv 都可能被读），三处一起改才稳
        putenv('APP_URL='.$appUrl);
        $_ENV['APP_URL'] = $_SERVER['APP_URL'] = $appUrl;

        if ($publicUrl === null) {
            putenv('WS_PUBLIC_URL'); // 无 "=" 即删除
            unset($_ENV['WS_PUBLIC_URL'], $_SERVER['WS_PUBLIC_URL']);
        } else {
            putenv('WS_PUBLIC_URL='.$publicUrl);
            $_ENV['WS_PUBLIC_URL'] = $_SERVER['WS_PUBLIC_URL'] = $publicUrl;
        }

        try {
            $config = require config_path('websocket.php');

            return (string) $config['public_url'];
        } finally {
            $this->restoreEnv('APP_URL', $originApp);
            $this->restoreEnv('WS_PUBLIC_URL', $originWs);
        }
    }

    private function restoreEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    /** 顶栏铃铛必须是 Vue 组件挂载点，而不是原生 JS 轮询的 data 钩子 */
    public function test_layout_mounts_notification_bell_component(): void
    {
        $admin = $this->admin();

        $html = (string) $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('data-component="notification-bell"', $html);
        $this->assertStringNotContainsString('data-notification-entry', $html, '原生轮询钩子应已移除');
    }
}
