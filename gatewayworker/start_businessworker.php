<?php

/**
 * BusinessWorker 进程：处理业务逻辑（票据换 uid、绑定、下发）
 */
use App\Gateway\Events;
use GatewayWorker\BusinessWorker;
use Workerman\Worker;

require_once __DIR__.'/../vendor/autoload.php';

$conf = require __DIR__.'/../config/websocket.php';

$worker = new BusinessWorker;
$worker->name = 'BusinessWorker';
$worker->count = max(1, $conf['server']['count']);
$worker->registerAddress = $conf['register_address'];
$worker->eventHandler = Events::class;

if (! defined('GLOBAL_START')) {
    Worker::runAll();
}
