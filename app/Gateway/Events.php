<?php

namespace App\Gateway;

use App\Support\WsTicket;
use GatewayWorker\Lib\Gateway;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Workerman\Worker;

/**
 * GatewayWorker 业务事件
 *
 * 连接建立后，客户端必须先用一次性票据换取 uid 绑定（type=auth）；
 * 未绑定 uid 的连接收不到任何推送。票据由 Laravel 侧 WsTicket 签发、
 * 在这里消费（消费即失效，防重放）。
 *
 * 票据默认跟随 CACHE_STORE，可用 `WS_TICKET_STORE` 单独指定（推荐 redis）。
 * 唯一硬性要求是跨进程共享——票据由 php-fpm 签发、本进程消费，array/null 驱动
 * 下票据永远兑不出来（启动时会打日志告警，deploy:check 也会阻断）。
 */
class Events
{
    /** 进程启动时引导一次 Laravel 内核，供票据校验复用缓存配置 */
    public static function onWorkerStart(Worker $worker): void
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        // 票据 store 不跨进程共享时，每个连接都会因鉴权失败被踢掉，
        // 且现象是「连上就断」、很难联想到缓存配置——启动时就说明白。
        if ($problem = WsTicket::problem()) {
            Log::error('[WS] '.$problem);
        }
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
