<?php

/**
 * Gateway 进程：对外提供 WebSocket 服务（浏览器连的就是这个）
 *
 * 生产环境建议由 Nginx 反代 /ws 到本机 2346 端口并启用 wss，
 * 此时 config('websocket.public_url') 配成 wss://域名/ws。
 */
use GatewayWorker\Gateway;
use Workerman\Worker;

require_once __DIR__.'/../vendor/autoload.php';

$conf = require __DIR__.'/../config/websocket.php';

$gateway = new Gateway('websocket://'.$conf['server']['listen']);
$gateway->name = 'Gateway';
$gateway->count = max(1, $conf['server']['count']);
$gateway->lanIp = $conf['server']['lan_ip'];
$gateway->startPort = $conf['server']['start_port'];
$gateway->registerAddress = $conf['register_address'];

// 心跳：60 秒内无任何数据则断开，防止半开连接堆积
$gateway->pingInterval = 25;
$gateway->pingNotResponseLimit = 2;
$gateway->pingData = '{"type":"ping"}';

if (! defined('GLOBAL_START')) {
    Worker::runAll();
}
