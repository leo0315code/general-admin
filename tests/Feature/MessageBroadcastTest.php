<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\NotificationBroadcast;
use App\Models\User;
use App\Support\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * 主动发送消息（群发站内通知）
 *
 * 关注四件事：
 * 1. 三档范围（指定用户 / 按角色 / 全员）解析出来的接收人是否准确；
 * 2. 停用账号不收消息；
 * 3. 站外链接必须被丢弃（不能拿系统消息发钓鱼链接）；
 * 4. 撤回要删干净，且权限分开。
 */
class MessageBroadcastTest extends TestCase
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

    /** 无消息权限的用户（editor 角色） */
    private function editor(): User
    {
        return User::factory()->create()->assignRole(User::ROLE_EDITOR);
    }

    /** 历史页需要 messages.manage */
    public function test_history_requires_manage_permission(): void
    {
        $this->actingAs($this->editor())->get(route('messages.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('messages.index'))->assertOk();
    }

    /** 发送页需要 messages.create */
    public function test_composer_requires_create_permission(): void
    {
        $this->actingAs($this->editor())->get(route('messages.create'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('messages.create'))->assertOk();
    }

    /** 发给指定用户：写入条数、关联与计数都要对 */
    public function test_sends_to_selected_users(): void
    {
        $admin = $this->admin();
        [$a, $b] = User::factory()->count(2)->create();
        User::factory()->create(); // 未被选中，不该收到

        $this->actingAs($admin)
            ->post(route('messages.store'), [
                'scope' => NotificationBroadcast::SCOPE_USERS,
                'user_ids' => [$a->id, $b->id],
                'title' => '维护通知',
                'content' => '今晚 22:00 维护',
            ])
            ->assertRedirect(route('messages.index'))
            ->assertSessionHas('success');

        $broadcast = NotificationBroadcast::query()->firstOrFail();

        $this->assertSame(2, $broadcast->recipients_count);
        $this->assertSame(2, Notification::query()->where('broadcast_id', $broadcast->id)->count());
        $this->assertDatabaseHas('notifications', ['user_id' => $a->id, 'title' => '维护通知', 'broadcast_id' => $broadcast->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $b->id, 'broadcast_id' => $broadcast->id]);
        $this->assertSame($admin->id, $broadcast->user_id, '要记录发送人，便于追溯');
    }

    /** 按角色发送：只发给该角色下的启用用户 */
    public function test_role_scope_skips_disabled_accounts(): void
    {
        $active = User::factory()->create()->assignRole(User::ROLE_EDITOR);
        $disabled = User::factory()->create(['status' => User::STATUS_DISABLED])->assignRole(User::ROLE_EDITOR);
        User::factory()->create(); // 无角色的普通用户，不该收到

        $this->actingAs($this->admin())
            ->post(route('messages.store'), [
                'scope' => NotificationBroadcast::SCOPE_ROLE,
                'role' => User::ROLE_EDITOR,
                'title' => '编辑部通知',
            ])
            ->assertRedirect(route('messages.index'));

        $broadcast = NotificationBroadcast::query()->firstOrFail();
        $expected = User::query()->role(User::ROLE_EDITOR)->where('status', User::STATUS_ACTIVE)->count();

        $this->assertSame($expected, $broadcast->recipients_count);
        $this->assertDatabaseHas('notifications', ['user_id' => $active->id, 'broadcast_id' => $broadcast->id]);
        // 停用账号不该收到（第三参数是连接名不是提示语，别传消息）
        $this->assertDatabaseMissing('notifications', ['user_id' => $disabled->id]);
    }

    /** 全员：所有启用用户 */
    public function test_all_scope_reaches_every_active_user(): void
    {
        User::factory()->count(3)->create();
        User::factory()->create(['status' => User::STATUS_DISABLED]);

        $this->actingAs($this->admin())
            ->post(route('messages.store'), [
                'scope' => NotificationBroadcast::SCOPE_ALL,
                'title' => '全员通知',
            ])
            ->assertRedirect(route('messages.index'));

        $expected = User::query()->where('status', User::STATUS_ACTIVE)->count();
        $broadcast = NotificationBroadcast::query()->firstOrFail();

        $this->assertSame($expected, $broadcast->recipients_count);
        $this->assertSame($expected, Notification::query()->where('broadcast_id', $broadcast->id)->count());
    }

    /** 站外链接必须被丢弃，站内路径要保留 */
    public function test_external_link_is_dropped(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->post(route('messages.store'), [
            'scope' => NotificationBroadcast::SCOPE_USERS,
            'user_ids' => [$user->id],
            'title' => '带链接',
            'link' => 'https://evil.example.com/phishing',
        ]);

        $this->assertNull(Notification::query()->first()->link, '站外地址不能进系统消息');

        $this->actingAs($this->admin())->post(route('messages.store'), [
            'scope' => NotificationBroadcast::SCOPE_USERS,
            'user_ids' => [$user->id],
            'title' => '站内链接',
            'link' => '/console/posts',
        ]);

        $this->assertSame('/console/posts', Notification::query()->latest('id')->first()->link);
    }

    /** 撤回：删掉这批通知并打时间戳，重复撤回要拒绝 */
    public function test_revoke_removes_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->post(route('messages.store'), [
            'scope' => NotificationBroadcast::SCOPE_USERS,
            'user_ids' => [$user->id],
            'title' => '待撤回',
        ]);

        $broadcast = NotificationBroadcast::query()->firstOrFail();
        $this->assertSame(1, Notification::query()->where('broadcast_id', $broadcast->id)->count());

        $this->actingAs($this->admin())
            ->delete(route('messages.revoke', $broadcast))
            ->assertSessionHas('success');

        $this->assertSame(0, Notification::query()->where('broadcast_id', $broadcast->id)->count());
        $this->assertNotNull($broadcast->fresh()->revoked_at);

        // 重复撤回：直接 422，别让人以为撤回了两次
        $this->actingAs($this->admin())
            ->delete(route('messages.revoke', $broadcast))
            ->assertStatus(422);
    }

    /** 撤回权限与发送权限分开 */
    public function test_revoke_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->post(route('messages.store'), [
            'scope' => NotificationBroadcast::SCOPE_USERS,
            'user_ids' => [$user->id],
            'title' => '不能乱撤',
        ]);

        $broadcast = NotificationBroadcast::query()->firstOrFail();

        $this->actingAs($this->editor())
            ->delete(route('messages.revoke', $broadcast))
            ->assertForbidden();

        $this->assertNull($broadcast->fresh()->revoked_at);
    }

    /** 选人搜索：只返回启用用户，且需要权限 */
    public function test_user_search_returns_active_users_only(): void
    {
        $target = User::factory()->create(['name' => '张三丰']);
        User::factory()->create(['name' => '张三丰二号', 'status' => User::STATUS_DISABLED]);

        $this->actingAs($this->admin())
            ->getJson(route('users.search', ['q' => '张三']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonCount(1, 'data');

        // 未登录：JSON 请求返回 401（注意 actingAs 在同一次测试里持续生效，要先登出）
        Auth::logout();
        $this->getJson(route('users.search', ['q' => '张三']))->assertUnauthorized();
    }

    /** 群发写入必须落库，哪怕推送通道不可用（WS 默认关闭即为此场景） */
    public function test_broadcast_persists_without_push_channel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Broadcaster::enabled(), '默认未启用 WS，正好验证降级');

        $this->actingAs($this->admin())->post(route('messages.store'), [
            'scope' => NotificationBroadcast::SCOPE_USERS,
            'user_ids' => [$user->id],
            'title' => '无推送也要收得到',
        ]);

        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'title' => '无推送也要收得到']);
        $this->assertSame(1, Notification::unreadCountFor($user->id), '未读计数要立刻可见，不能等缓存过期');
    }

    /** 校验：没选人 / 没填标题都要挡住，不能静默发出空消息 */
    public function test_validates_required_fields(): void
    {
        $this->actingAs($this->admin())
            ->post(route('messages.store'), ['scope' => NotificationBroadcast::SCOPE_USERS, 'title' => '没选人'])
            ->assertSessionHasErrors('user_ids');

        $this->actingAs($this->admin())
            ->post(route('messages.store'), ['scope' => NotificationBroadcast::SCOPE_ALL])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, NotificationBroadcast::query()->count());
    }
}
