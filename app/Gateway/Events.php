<?php

namespace App\Gateway;

use App\Support\WsTicket;
use GatewayWorker\Lib\Gateway;
use Illuminate\Contracts\Console\Kernel;
use Workerman\Worker;

/**
 * GatewayWorker 业务事件
 *
 * 连接建立后，客户端必须先用一次性票据换取 uid 绑定（type=auth）；
 * 未绑定 uid 的连接收不到任何推送。票据由 Laravel 侧 WsTicket 签发、
 * 在这里消费（消费即失效，防重放）。
 *
 * 注意：本进程只用到缓存（票据），未使用数据库；
 * 若 cache 驱动为 database，建议生产改 redis/file，避免长连接断开。
 */
class Events
{
    /** 进程启动时引导一次 Laravel 内核，供票据校验复用缓存配置 */
    public static function onWorkerStart(Worker $worker): void
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    /** 收到客户端消息：目前只需处理 auth（票据换 uid） */
    public static function onMessage(string $clientId, mixed $message): void
    {
        $data = json_decode((string) $message, true);

        if (! is_array($data) || ($data['type'] ?? '') !== 'auth') {
            return;
        }

        $uid = WsTicket::consume((string) ($data['ticket'] ?? ''));

        if ($uid === null) {
            Gateway::sendToClient($clientId, json_encode(['type' => 'auth', 'ok' => false], JSON_UNESCAPED_UNICODE));
            Gateway::closeClient($clientId);

            return;
        }

        Gateway::bindUid($clientId, $uid);
        Gateway::sendToClient($clientId, json_encode(['type' => 'auth', 'ok' => true, 'uid' => $uid], JSON_UNESCAPED_UNICODE));
    }

    public static function onConnect(string $clientId): void
    {
        // 连接后等待 auth，不做任何绑定
    }

    public static function onClose(string $clientId): void
    {
        // uid 绑定由 Gateway 自动清理
    }
}
