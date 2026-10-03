<?php

/**
 * Register 服务（Gateway 与 BusinessWorker 的中枢，必须先启动）
 *
 * 启动：php gatewayworker/start.php start
 */
use Workerman\Worker;

require_once __DIR__.'/../vendor/autoload.php';

$register = new Worker('text://0.0.0.0:1236');
$register->name = 'Register';

if (! defined('GLOBAL_START')) {
    Worker::runAll();
}
