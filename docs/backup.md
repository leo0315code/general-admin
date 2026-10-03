# 数据库备份与恢复

> 使用 [`spatie/laravel-backup`](https://github.com/spatie/laravel-backup) v10（`backup:run` / `backup:clean` / `backup:monitor`）。
> 本文记录本项目的**定制取舍**与**演练过的恢复步骤**，通用用法见官方文档。

---

## 一、设计取舍

| 决策 | 选择 | 理由 |
|---|---|---|
| 备份范围 | **只备份数据库** | 代码与应用配置已在 git 版本化；`storage` 下附件会持续增长，打进 zip 会让备份体积失控，恢复时还可能用旧代码压掉新代码 |
| 存储位置 | `backups` 磁盘（`storage/app/private/backups`） | 独立目录便于统计占用；**不提供 URL，无法被 HTTP 直接下载** |
| 是否入 git | **否**（已被 `.gitignore` 覆盖） | 避免每次备份都污染工作区与仓库体积 |
| 告警范围 | **只告警异常** | 成功通知每天一封会把真正失败淹没；想确认状态用 `backup:list` |
| mysqldump 路径 | **运行时自动探测** | 见下节，换机器不需要改任何代码 |

---

## 二、mysqldump 路径：为什么不用改配置

由 `app/Support/DumpBinary.php` 按优先级探测，**全程只用 `is_executable()`，不调 `shell_exec`**（因此在禁用命令函数的环境同样可用）：

1. `DB_DUMP_BINARY_PATH` 环境变量 —— 运维显式接管时才需要填
2. `PATH` 环境变量中的目录
3. 候选目录表（内置 ServBay / MAMP / XAMPP / Homebrew / 宝塔 `/www/server/mysql/bin` / Docker 常用路径等）
4. 都找不到返回 `null`（宁可报错，也不产出静默的空备份）

结果由 `AppServiceProvider` 在**命令行环境**下注入到 `database.connections.mysql.dump`（Web 请求不探测，零开销）。

新增机器的 Bin 位置只需往 `DumpBinary::CANDIDATE_DIRS` 追加一行。

> 本机实测：`mysqldump` 不在 `PATH` 里，仍自动命中 `/Applications/ServBay/package/mysql/current/bin`。

---

## 三、常用命令

```bash
php artisan backup:run        # 立即备份一次（先看日志确认 dump 成功）
php artisan backup:list       # 查看备份清单、健康状态、占用空间
php artisan backup:clean      # 按保留策略清理旧备份
php artisan backup:monitor    # 健康检查（超期 / 超容量则发邮件告警）
```

## 四、自动调度

`routes/console.php` 已注册（依赖 cron 执行 `php artisan schedule:run`）：

| 时间 | 命令 | 作用 |
|---|---|---|
| 02:00 | `backup:run` | 生成备份 |
| 02:30 | `backup:clean` | 清理超期备份 |
| 09:00 | `backup:monitor` | 健康检查告警 |

## 五、保留策略（`config/backup.php`）

defaults 策略是「近密远疏」，且**永远不会删掉最新一份**：

| 阶段 | 保留 |
|---|---|
| 7 天内 | 全部（`BACKUP_KEEP_ALL_DAYS`） |
| 之后 16 天 | 每天一份 |
| 之后 8 周 | 每周一份 |
| 之后 4 个月 | 每月一份 |
| 之后 2 年 | 每年一份 |
| 总量超 5000 MB | 删最旧的直到达标（`BACKUP_MAX_STORAGE_MB`） |

---

## 六、🚨 恢复演练（务必定期做）

**没演练过的备份等于没有备份。** 以下步骤已在本机实际跑通。

```bash
# 1. 找最新备份
php artisan backup:list
ZIP=storage/app/private/backups/*/$(ls -t storage/app/private/backups/*/ | head -1)

# 2. 解包并解压（SQL 是 gzip 压缩的，后缀 .sql.gz）
mkdir -p /tmp/restore && unzip -q -o $ZIP -d /tmp/restore
gunzip -k /tmp/restore/db-dumps/*.sql.gz

# 3. 【重要】先恢复到临时库验证，不要直接覆盖生产库
php artisan tinker   # 或直接用 mysql 客户端
mysql -u用户 -p -e "CREATE DATABASE ga_restore_drill CHARACTER SET utf8mb4;"
mysql -u用户 -p ga_restore_drill < /tmp/restore/db-dumps/mysql-general_admin.sql

# 4. 校验数据条数符合预期，再决定是否导入正式库
mysql -u用户 -p -e "SELECT COUNT(*) FROM ga_restore_drill.users;"

# 5. 确认无误后才导入生产库，并清理临时库
mysql -u用户 -p -e "DROP DATABASE ga_restore_drill;"
```

**本机演练记录**：98,318 字节 SQL → 恢复到临时库得到 **23 张表 / 16 用户 / 38 菜单 / 199 日志 / 23 文章**，与源库一致。

## 七、生产注意事项

| 项 | 说明 |
|---|---|
| 异地备份 | **已内置阿里云 OSS 支持**：配齐 `OSS_*` 四项即自动双写（本地 + OSS），见下节。只存在本机等于没备份（磁盘故障一起丢） |
| 凭据 | 备份含全量数据，存储桶权限务必私有；`.env` 里的 `DB_PASSWORD` 不得入库 |
| Windows | **spatie 官方明确不支持 Windows 服务器**。若必须部署 Windows，此方案需另行评估（数据库 dump 部分可能可用，但不受官方支持） |
| 容器内缺 mysqldump | 备份会失败并由 `backup:monitor` 告警，不会静默产出空备份 |
| 备份目录名 | 目录名取自 `app.name`（当前含中文），Linux 下 UTF-8 正常；如遇乱码可固定为英文 |
| 告警收件人 | 配 `BACKUP_NOTIFY_EMAIL`（缺省回落 `MAIL_FROM_ADDRESS`） |

## 七·五、异地备份（阿里云 OSS）

> **只存本机磁盘的备份等于没备份** —— 磁盘故障会让备份和数据一起丢。

### 启用方式

在 `.env` 配齐四项即自动启用，**不需要改任何代码**：

```dotenv
OSS_ACCESS_KEY=LTAI********
OSS_SECRET_KEY=********
OSS_ENDPOINT=https://oss-cn-hangzhou.aliyuncs.com
OSS_BUCKET=my-backup-bucket
OSS_PREFIX=backups          # 可选，桶内目录前缀
OSS_IS_CNAME=false          # 用自定义域名绑定 bucket 时才置 true
```

`App\Support\BackupTarget` 会在每次备份时判断：

| 条件 | 行为 |
|---|---|
| 四项凭证齐全 + `iidestiny/flysystem-oss` 已装 | 本地 + OSS **双写**，`continue_on_failure=true` |
| 缺任一项，或驱动未安装 | 安静退回**纯本地备份**（不会因异地配置问题拖垮本地备份） |

启用后 `backup:run` 会依次写两个目标，`backup:monitor` 也**同时监控两个盘**的新鲜度——OSS 长期写不进去会被健康检查发现。

### 验证是否生效

```bash
php artisan backup:run      # 应看到两次 "Successfully copied zip to disk named ..."
php artisan backup:monitor  # 两个盘都应 healthy
php artisan tinker --execute="echo json_encode(config('backup.backup.destination.disks'));"
# 期望：["backups","backup_offsite"]
```

### 实现说明

- 驱动：`iidestiny/flysystem-oss`（Flysystem v3 适配器）。该包不带 Laravel ServiceProvider，由 `BackupTarget::registerOssDriver()` 在 `AppServiceProvider` 里注册 `oss` 驱动
- 目录前缀走适配器构造参数（`oss_prefix`），**不用 Laravel 的 `prefix` 磁盘配置**：当前依赖组合（flysystem 3.36 + Laravel 13.31）下那条路径会引用不存在的 `League\Flysystem\PathPrefixing\PathPrefixedAdapter`，配了会直接报错
- 凭证只存 `.env`，不入库；OSS 存储桶权限务必设为私有

## 八、相关环境变量（`.env.production.example`）

```
BACKUP_KEEP_ALL_DAYS=7
BACKUP_MAX_STORAGE_MB=5000
BACKUP_MAX_AGE_DAYS=1
BACKUP_NOTIFY_EMAIL=
DB_DUMP_BINARY_PATH=        # 留空＝自动探测

# 异地备份（阿里云 OSS），四项齐全才启用
OSS_ACCESS_KEY=
OSS_SECRET_KEY=
OSS_ENDPOINT=
OSS_BUCKET=
OSS_PREFIX=backups
OSS_IS_CNAME=false
```
