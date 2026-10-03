<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 通知中心（站内消息）
 *
 * 覆盖三件事：
 * 1. 通道本身：列表 / 未读筛选 / 标记已读 / 全部已读 / 删除；
 * 2. 数据范围：通知是个人数据，越权访问他人通知必须 403（Policy）；
 * 3. 业务接入：创建账号、重置密码会落站内通知（且不依赖邮箱）。
 */
class NotificationCenterTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function notify(User $user, array $overrides = []): Notification
    {
        return Notification::query()->create(array_merge([
            'user_id' => $user->id,
            'type' => Notification::TYPE_ACCOUNT_CREATED,
            'title' => '测试通知',
            'content' => '测试正文',
            'read_at' => null,
        ], $overrides));
    }

    public function test_index_lists_only_own_notifications(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        $mine = $this->notify($admin, ['title' => '我的通知']);
        $theirs = $this->notify($other, ['title' => '别人的通知']);

        $html = (string) $this->actingAs($admin)->get(route('notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('我的通知', $html);
        $this->assertStringNotContainsString('别人的通知', $html);
        $this->assertNotNull($mine->id);
        $this->assertNotNull($theirs->id);
    }

    public function test_index_can_filter_unread_only(): void
    {
        $admin = $this->admin();

        $this->notify($admin, ['title' => '未读的那条']);
        $this->notify($admin, ['title' => '已读的那条', 'read_at' => now()]);

        $unreadHtml = (string) $this->actingAs($admin)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('未读的那条', $unreadHtml);
        $this->assertStringNotContainsString('已读的那条', $unreadHtml);

        $allHtml = (string) $this->actingAs($admin)->get(route('notifications.index'))->getContent();

        $this->assertStringContainsString('已读的那条', $allHtml);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $admin = $this->admin();
        $item = $this->notify($admin);

        $this->actingAs($admin)
            ->patch(route('notifications.read', $item))
            ->assertRedirect();

        $this->assertNotNull($item->fresh()->read_at);
    }

    public function test_mark_all_as_read_only_touches_own_unread(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        $this->notify($admin);
        $this->notify($admin);
        $theirs = $this->notify($other);

        $this->actingAs($admin)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, Notification::query()->forUser($admin)->unread()->count());
        $this->assertNull($theirs->fresh()->read_at, '全部已读不应影响他人通知');
    }

    public function test_owner_can_delete_notification(): void
    {
        $admin = $this->admin();
        $item = $this->notify($admin);

        $this->actingAs($admin)
            ->delete(route('notifications.destroy', $item))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $item->id]);
    }

    public function test_cannot_read_or_delete_others_notification(): void
    {
        $admin = $this->admin();
        $victim = User::factory()->create();
        $foreign = $this->notify($victim);

        $this->actingAs($admin)->patch(route('notifications.read', $foreign))->assertForbidden();
        $this->actingAs($admin)->delete(route('notifications.destroy', $foreign))->assertForbidden();

        $this->assertNull($foreign->fresh()->read_at);
        $this->assertDatabaseHas('notifications', ['id' => $foreign->id]);
    }

    public function test_guest_must_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->get(route('notifications.unread-count'))->assertRedirect(route('login'));
    }

    public function test_unread_count_endpoint_returns_own_count(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();

        $this->notify($admin);
        $this->notify($admin);
        $this->notify($admin, ['read_at' => now()]);
        $this->notify($other);

        $this->actingAs($admin)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 2]);
    }

    public function test_creating_user_sends_in_app_notification_even_without_email(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => '无邮箱用户',
                'email' => null,
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
            ])
            ->assertRedirect(route('users.index'));

        $created = User::query()->where('name', '无邮箱用户')->firstOrFail();

        // 邮件通道因无邮箱会跳过，但站内通知必须照常落地
        $this->assertDatabaseHas('notifications', [
            'user_id' => $created->id,
            'type' => Notification::TYPE_ACCOUNT_CREATED,
        ]);
    }

    public function test_resetting_password_sends_in_app_notification(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.reset-password', $target), [
                'new_password' => 'Password123',
                'new_password_confirmation' => 'Password123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $target->id,
            'type' => Notification::TYPE_PASSWORD_RESET,
        ]);
    }

    public function test_notifier_normalizes_same_site_url_and_rejects_external_link(): void
    {
        $admin = $this->admin();

        $internal = Notifier::send($admin, 'test', '站内链接', null, route('users.index'));
        $this->assertNotNull($internal->link);
        $this->assertStringStartsWith('/', $internal->link);
        $this->assertStringNotContainsString(config('app.url'), $internal->link, '本站 URL 应归一化为相对路径');

        $external = Notifier::send($admin, 'test', '站外链接', null, 'https://evil.example.com/phishing');
        $this->assertNull($external->link, '站外地址必须被丢弃，避免通知变成钓鱼入口');

        $protocolRelative = Notifier::send($admin, 'test', '协议相对', null, '//evil.example.com');
        $this->assertNull($protocolRelative->link);
    }

    /** 顶栏未读数走缓存：连续两次取数只查一次库 */
    public function test_unread_count_is_cached_and_query_happens_once(): void
    {
        $admin = $this->admin();
        $this->notify($admin);
        $this->notify($admin);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->assertSame(2, Notification::unreadCountFor($admin->id));
        Notification::unreadCountFor($admin->id);
        Notification::unreadCountFor($admin->id);

        $this->assertSame(1, $queries, '缓存生效后同一用户只应查一次库');
    }

    /** 标记已读后未读数必须立刻下降（缓存被主动失效） */
    public function test_unread_count_reflects_read_immediately(): void
    {
        $admin = $this->admin();
        $this->notify($admin);
        $this->notify($admin);

        $this->assertSame(2, Notification::unreadCountFor($admin->id));

        Notification::query()->forUser($admin)->first()->markAsRead();
        Notification::forgetUnreadCount($admin->id);

        $this->assertSame(1, Notification::unreadCountFor($admin->id));
    }

    /** Notifier 发新通知后顶栏计数立刻更新，不用等缓存过期 */
    public function test_notifier_invalidates_unread_cache(): void
    {
        $admin = $this->admin();

        $this->assertSame(0, Notification::unreadCountFor($admin->id));

        Notifier::send($admin, Notification::TYPE_ACCOUNT_CREATED, '新账号');

        $this->assertSame(1, Notification::unreadCountFor($admin->id), '新通知应立即可见，不能等 30 秒缓存过期');
    }

    public function test_deleting_user_removes_their_notifications(): void
    {
        $victim = User::factory()->create();
        $item = $this->notify($victim);

        $victim->delete(); // 用户软删除：通知随外键保留
        $this->assertDatabaseHas('notifications', ['id' => $item->id]);

        $victim->forceDelete(); // 彻底删除：外键级联清理
        $this->assertDatabaseMissing('notifications', ['id' => $item->id]);
    }
}
