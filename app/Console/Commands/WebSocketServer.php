<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * WebSocket 服务（GatewayWorker）启停封装
 *
 * 用法：
 *   php artisan ws start     后台启动
 *   php artisan ws stop      停止
 *   php artisan ws restart   重启
 *   php artisan ws status    查看进程状态
 *
 * 统一入口便于运维记忆，也避免手敲 workerman 的 start.php 参数。
 */
class WebSocketServer extends Command
{
    /** @var string */
    protected $signature = 'ws {action=status : start|stop|restart|status}';

    /** @var string */
    protected $description = '启停 WebSocket 服务（GatewayWorker）';

    /** @var list<string> 允许的指令 */
    private const ACTIONS = ['start', 'stop', 'restart', 'status'];

    public function handle(): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, self::ACTIONS, true)) {
            $this->error('未知指令：'.$action.'（可用：'.implode('|', self::ACTIONS).'）');

            return self::FAILURE;
        }

        if (! (bool) config('websocket.enabled')) {
            $this->warn('WS_ENABLED 未开启：推送未启用，页面会退化为轮询未读数。');
        }

        $args = $action === 'start' ? ['start', '-d'] : [$action];

        $process = new Process(
            array_merge([PHP_BINARY, base_path('gatewayworker/start.php')], $args),
            base_path()
        );

        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer) {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
