<?php

/**
 * GatewayWorker 统一启动入口（Register + Gateway + BusinessWorker 一并启动）
 *
 * 日常请用 Artisan 封装：php artisan ws start|stop|restart|status
 */
use Workerman\Worker;

define('GLOBAL_START', 1);

require_once __DIR__.'/../vendor/autoload.php';

require_once __DIR__.'/start_register.php';
require_once __DIR__.'/start_gateway.php';
require_once __DIR__.'/start_businessworker.php';

Worker::runAll();
