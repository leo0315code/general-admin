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

// 数据库备份（spatie/laravel-backup）：产物落在 backups 磁盘，已被 gitignore
// 只备份数据库——代码在 git 里，打包站点目录既占空间、恢复时又容易压掉新代码
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();

// 旧备份清理：按 config/backup.php 的 cleanup 保留策略执行（7 天全留 → 日/周/月/年递减）
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping();

// 备份健康检查：最新备份超过 BACKUP_MAX_AGE_DAYS 天或占用超阈值时，
// 按 config/backup.php 的 notifications 配置发邮件告警（成功事件刻意不告警，避免每天一封）
Schedule::command('backup:monitor')->dailyAt('09:00')->withoutOverlapping();

// 附件清理：过期附件 + 磁盘孤儿文件（保留天数见 config uploads.prune_days）
Schedule::command('attachments:prune')->dailyAt('03:20')->withoutOverlapping();

Schedule::call(function () {
    // 仅清理已过期的缓存行（不误删有效缓存；expiration 为 unix 时间戳）
    DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
    DB::table('cache_locks')->where('expiration', '<', now()->timestamp)->delete();
})->dailyAt('03:10')->name('prune-expired-cache')->withoutOverlapping();
