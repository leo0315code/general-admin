# 生产部署检查单

> 上线前逐项核对。当前 `.env` 为本地开发配置，**不可直接用于生产**。

## 0. 起点：用生产模板生成 `.env`

```bash
cp .env.production.example .env     # 模板已把生产该关的都关好（占位符 REPLACE_ME 需全部替换）
php artisan key:generate --force
```

> 模板文件 `.env.production.example` 已在仓库中（`.env.production` 本身被 gitignore，防止真实密钥入库）。

## 1. 必改项（P0，阻断级）

| 项 | 本地值（错误示范） | 生产要求 |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`**（开启会泄露堆栈/配置/密码线索） |
| `APP_KEY` | — | 必须重新生成：`php artisan key:generate --force`，且不得提交到仓库 |
| `DB_PASSWORD` | `123456` | 强随机密码（如 `openssl rand -base64 24`），仅存于服务器密管 |
| `SESSION_ENCRYPT` | `false` | **`true`**（会话含登录态与角色信息，加密落库） |
| `SESSION_SECURE_COOKIE` | 未设 | `true`（仅 HTTPS 传输，配合下方强制 HTTPS） |

## 2. 环境与网络

- [ ] 全站 HTTPS，并确认 `SecurityHeaders` 中间件生效（`APP_ENV=production` 时自动追加 HSTS + CSP）
- [ ] CSP 已收紧为 `script-src 'self' 'nonce-…'`（每请求随机 nonce）：**新增内联脚本必须带 `nonce="{{ Vite::cspNonce() }}"`，否则浏览器拦截导致白屏**；`SecurityHeadersTest` 会拦截漏加 nonce 的情况
- [ ] `APP_URL` 与实际域名一致（影响生成链接、密码重置）
- [ ] 生产只放行 `public/` 目录（nginx root 指向 `public`，禁止访问 `storage/`、`vendor/`、`.env`）
- [ ] `storage/`、`bootstrap/cache` 目录可写：`php artisan storage:link`（若有公开磁盘）

## 3. 数据库

```bash
php artisan migrate --force          # 生产禁止交互确认
php artisan config:cache             # 或 config:route:view 三件套
```

- [ ] 确认 `.env` 中数据库账号仅授权本项目库（最小权限，禁用 root）
- [ ] 首个管理员创建后立即改默认密码（`EnsurePasswordChanged` 会强制）
- [ ] **升级后重新同步权限种子**：菜单树是权限的唯一来源（「菜单即权限」），新增按钮级权限节点后需执行 `php artisan db:seed --class=MenuPermissionSeeder`（幂等，可重复执行），再 `php artisan permission:cache-reset`。否则新权限在权限表中不存在，非 admin 角色会把对应操作全部 403

## 4. 缓存与限流（依赖 CACHE_STORE）

- [ ] 当前 `CACHE_STORE=database`（默认）。登录限流（`RateLimiter`）与验证码计数都走缓存——**生产建议 Redis**：`CACHE_STORE=redis` + `REDIS_*` 配置；无 Redis 时 database 也可用，但高频限流会给 DB 加压
- [ ] Session driver 已是 `database`，可保持；高并发再评估 redis

## 5. 上线后验证

- [ ] 登录一次：验证验证码、限流（连续 5 次错误密码应被锁 1 分钟）
- [ ] 响应头检查：`curl -sI https://域名 | grep -iE "x-frame|x-content|referrer|strict|content-security"`
  - 生产环境应看到 `Strict-Transport-Security` 与 `Content-Security-Policy`（本地环境没有这两项是正常的）
- [ ] 操作日志：后台做一次写操作，`logs` 页面应出现记录
- [ ] 抽查 `php artisan about` 与 `storage/logs/laravel.log` 无异常刷屏

## 6. 定期维护

- [ ] 日志轮转已内置（P1 审计时落地的清理任务），确认 `schedule:run` 已进 crontab：
  `* * * * * cd /项目路径 && php artisan schedule:run >> /dev/null 2>&1`
- [ ] 依赖安全更新：`composer audit` / `npm audit --omit=dev` 定期跑（CI 已含）
