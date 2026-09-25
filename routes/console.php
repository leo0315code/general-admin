<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 定时任务调度（需服务器配置 cron 执行 `php artisan schedule:run`）
|--------------------------------------------------------------------------
|
| 建议 crontab：
|   * * * * * cd /path/to/general-admin && php artisan schedule:run >> /dev/null 2>&1
|
| - model:prune    清理已过保留期的数据（操作日志保留 90 天，见 OperationLog）
| - 过期缓存清理    database 驱动的 cache 表不会自动删除过期行，需定期物理清理
|
| 注意：缓存清理仅在 CACHE_STORE=database 时必要；如改用 redis 可删除该调度项。
*/

Schedule::command('model:prune')->dailyAt('03:00');

Schedule::call(function () {
    // 仅清理已过期的缓存行（不误删有效缓存；expiration 为 unix 时间戳）
    DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
    DB::table('cache_locks')->where('expiration', '<', now()->timestamp)->delete();
})->dailyAt('03:10')->name('prune-expired-cache')->withoutOverlapping();
