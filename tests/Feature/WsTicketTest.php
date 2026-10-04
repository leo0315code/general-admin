<?php

namespace Tests\Feature;

use App\Support\WsTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * WS 连接票据的存储与一次性语义
 *
 * 与 WebSocketPushTest 的分工：那边测「推送链路静默降级」，这里测「票据本身
 * 能不能安全兑现」。重点是两个容易踩空的地方：
 * 1. 票据必须由 php-fpm 签发、常驻 Worker 消费，落在不跨进程共享的驱动上就永远兑不出来；
 * 2.「消费即失效」不能靠 Cache::pull —— 它是 get + forget 两条命令，并发下会漏。
 */
class WsTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_follows_default_cache_store_when_not_configured(): void
    {
        config(['websocket.ticket_store' => null]);

        $this->assertSame(config('cache.default'), WsTicket::storeName());
    }

    public function test_ticket_store_can_be_pinned_explicitly(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $ticket = WsTicket::issue(42);

        // 写到指定的 store，而不是默认 store
        $this->assertSame(42, Cache::store('array')->get('ws.ticket.'.$ticket));
        $this->assertSame('array', WsTicket::storeName());
    }

    /** array / null 不跨进程共享：票据签发后 Worker 根本读不到 */
    public function test_unshared_stores_are_rejected(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $this->assertFalse(WsTicket::usable());
        $this->assertStringContainsString('WS_TICKET_STORE', (string) WsTicket::problem());

        config(['websocket.ticket_store' => 'null']);

        $this->assertFalse(WsTicket::usable());
    }

    /** database 能用，但代价最高——降级为建议而非阻断 */
    public function test_database_store_is_usable_but_discouraged(): void
    {
        config(['websocket.ticket_store' => 'database']);

        $this->assertTrue(WsTicket::usable());
        $this->assertStringContainsString('wait_timeout', (string) WsTicket::problem());
    }

    public function test_shared_stores_have_no_problem(): void
    {
        config(['websocket.ticket_store' => 'file']);

        $this->assertTrue(WsTicket::usable());
        $this->assertNull(WsTicket::problem());
    }

    public function test_ticket_is_redeemable_exactly_once(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $ticket = WsTicket::issue(7);

        $this->assertSame(7, WsTicket::consume($ticket));
        $this->assertNull(WsTicket::consume($ticket), '票据必须一次性，防止重放');
        $this->assertNull(Cache::store('array')->get('ws.ticket.'.$ticket), '兑现后不应留下票据值');
    }

    /**
     * 并发下「赢不了占位」的一方必须被拒绝。
     *
     * 真正的并发在单测里造不出来，所以直接模拟「另一个进程先抢到占位」后的状态——
     * 这正是 Cache::pull 会漏、而 add 能挡住的场景。
     */
    public function test_losing_the_spent_marker_blocks_redemption(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $ticket = WsTicket::issue(7);
        Cache::store('array')->put('ws.ticket.'.$ticket.':spent', 1, 60);

        $this->assertNull(WsTicket::consume($ticket), '占位已被抢走时应拒绝兑现');
    }

    /** 占位键按票据隔离：A 兑现过不影响 B */
    public function test_spent_markers_are_scoped_per_ticket(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $first = WsTicket::issue(7);
        $second = WsTicket::issue(9);

        $this->assertSame(7, WsTicket::consume($first));
        $this->assertSame(9, WsTicket::consume($second), '另一张票据不应被前一张的占位挡住');
    }

    /** 伪造票据取不到值：直接拒绝，且一个键都不写（防缓存被灌满） */
    public function test_forged_ticket_writes_nothing(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $forged = str_repeat('a', 40);

        $this->assertNull(WsTicket::consume($forged));
        $this->assertNull(Cache::store('array')->get('ws.ticket.'.$forged));
        $this->assertNull(Cache::store('array')->get('ws.ticket.'.$forged.':spent'), '无效票据不应产生占位键');
    }

    public function test_malformed_ticket_is_rejected_before_any_lookup(): void
    {
        config(['websocket.ticket_store' => 'array']);

        $this->assertNull(WsTicket::consume(''));
        $this->assertNull(WsTicket::consume('../../etc/passwd'));
        $this->assertNull(WsTicket::consume(str_repeat('a', 10)), '长度不足应直接拒绝');
    }
}
