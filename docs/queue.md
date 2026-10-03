# 队列（Queue）使用与运维

## 当前状态

- 驱动：`QUEUE_CONNECTION=database`（jobs 表已迁移，无需 Redis）
- 已入队的任务：**账号凭据邮件**（`AccountCredentials`，implements `ShouldQueue`）
  - 新建账号 / 重置密码时自动入队，SMTP 发信不阻塞后台操作
  - 测试环境（phpunit.xml 配 `QUEUE_CONNECTION=sync`）仍同步发送，行为不变
- **刻意不入队**的：
  - **站内通知群发**（`Notifier::dispatch`）：已用分块批量 insert + 一次推送，几百人毫秒级完成；
    且发送方需要「已发给 N 人」的即时反馈，入队反而体验变差
  - 附件清理 / 日志清理：走定时调度（schedule），不需要队列

## 为什么需要 worker

database 队列的任务**不会自己执行**，必须有一个常驻进程消费：

```bash
php artisan queue:work --sleep=3 --tries=3
```

不跑 worker 的后果：邮件会一直滞留在 jobs 表，看起来"发送失败"（实际是没被消费）。

## 常驻（生产必配）

supervisor 示例（`/etc/supervisor/conf.d/general-admin-queue.conf`）：

```ini
[program:general-admin-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/general-admin/artisan queue:work --sleep=3 --tries=3
directory=/var/www/general-admin
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/general-admin/storage/logs/queue-worker.log
```

systemd 亦可，原则：`autorestart=true`、日志落 storage/logs。

## 失败与重试

- 单任务最多重试 `--tries=3`（可在命令里调）；重试间隔由 `queue.failed` 相关配置决定
- 超过重试次数进入 `failed_jobs` 表（已迁移）：`php artisan queue:failed` 查看，`queue:retry all` 重放
- 邮件通知的 `toMail()` 抛错即视为任务失败 → 重试；`notifyCredentials()` 里调用方的 catch
  只兜同步场景，入队后由 worker 的重试机制兜底

## 安全注意

- **jobs / failed_jobs 表会短暂存储明文初始密码**（通知对象被整体序列化）。
  建议：数据库账号按最小权限配置；必要时部署后清理 failed_jobs 中的敏感负载。
- 密码泄露路径除 jobs 表外与同步发送完全一致（邮件本身含密码），
  两个场景都强制/提示「首次登录立即改密」，见 `AccountCredentials` 注释。

## 把新任务入队（约定）

```php
// 1. 任务类 implements ShouldQueue + use Queueable（Notification 同理）
// 2. 测试里验证序列化往返（参考 AccountNotificationTest::test_database_queue_roundtrip_serializes_safely）
// 3. 文档此处登记
```

原则：**用户需要即时结果的保持同步**（群发/导入回显），只有"慢且可异步"的才入队（邮件、批量通知）。
