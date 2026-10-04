# WebSocket 实时推送（GatewayWorker）

站内通知的实时通道。**未启用时铃铛自动退化为 60 秒轮询，功能完全不受影响**——这是设计底线：推送挂了不能连累业务。

## 架构

```
浏览器 NotificationBell.vue
        │  1) POST /console/ws/ticket 换一次性票据
        │  2) ws://host:2346 建连，发 {type:'auth', ticket}
        ▼
Gateway(2346) ──► BusinessWorker（Events.php 消费票据 → bindUid）
        ▲
        │  3) GatewayClient 推送（sendToUid）
Laravel Notifier（发通知时触发 Broadcaster）
```

## 启用步骤

1. `.env` 打开开关：

```env
WS_ENABLED=true
WS_REGISTER_ADDRESS="127.0.0.1:1236"
WS_LISTEN="0.0.0.0:2346"
WS_WORKER_COUNT=2
WS_TICKET_TTL=60
WS_TICKET_STORE=redis      # 票据存哪，见下节「票据存储」
```

浏览器连的地址**不用配**：默认由 `APP_URL` 推导——`https://域名` → `wss://域名/ws`、`http://域名` → `ws://域名/ws`，端口沿用（如 `http://localhost:8000` → `ws://localhost:8000/ws`）。生产配好 Nginx 的 `/ws` 反代即可，见下节。

只有 WS 与 HTTP 不同域名/端口时才写 `WS_PUBLIC_URL`，典型是本地开发直连 Gateway 明文端口：`WS_PUBLIC_URL="ws://127.0.0.1:2346"`。

2. 启动服务（Artisan 封装，别手敲 start.php）：

```bash
php artisan ws start     # 后台启动
php artisan ws status
php artisan ws restart
php artisan ws stop
```

### ws 还是 wss：不用纠结，前端按浏览器环境自动选

协议不写死。后端下发的地址会经 `resolveWsUrl()`（前端纯函数，有单测）归一化：

| 后端地址 | https 页面 | http 页面（**没有 SSL**） |
|---|---|---|
| 不配，或写相对路径 `/ws` | `wss://当前域名/ws` | `ws://当前域名/ws` |
| `ws://当前域名/ws`（同域名） | 自动升级 `wss` | 保持 `ws` |
| `wss://当前域名/ws`（同域名） | 保持 `wss` | 降级 `ws` |
| `ws://127.0.0.1:2346`（跨域名回环） | 保持 `ws`（浏览器白名单） | 保持 `ws` |
| `ws://其它主机:2346`（跨域名） | 升级 `wss`（否则被当混合内容拦掉） | 保持 `ws` |

结论：**没有 SSL 的环境什么都不用配**——页面是 http 就用 `ws://` 直连 Gateway；只有「https 页面 + 跨主机明文」这一种组合必须套一层 TLS 反代。

4. Nginx 反代（仅在需要 wss 时：/ws → 本机 2346）：

```nginx
location /ws {
    proxy_pass http://127.0.0.1:2346;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 3600s;
}
```

5. 常驻：给服务器加 cron（`schedule:run` 只管定时任务，**WS 进程用 systemd/supervisor 托管**）：

```ini
[program:general-admin-ws]
command=/path/to/php /path/to/general-admin/gatewayworker/start.php start
autostart=true
autorestart=true
```

## 安全设计

| 点 | 做法 |
|---|---|
| 身份识别 | WS 握手拿不到主站会话 Cookie → 登录用户先换**一次性票据**（60 秒有效），Worker 侧原子消费，防重放（见下节） |
| 票据格式 | 只接受 20–80 位字母数字，非法值直接拒绝并断开 |
| 未绑定不推送 | 未完成 auth 的连接不会 bindUid，收不到任何消息 |
| 故障隔离 | Register 不可达只记日志返回 `false`；通知照样落库（有回归测试守着） |
| 数据为准 | 推送只当「有新消息」的信号，客户端收到后仍会拉一次未读数接口校正，推送丢包不会导致计数失真 |

## 票据存储

票据由 **php-fpm 进程签发、常驻 Worker 进程消费**，两者内存不互通，所以唯一硬性要求是**跨进程共享**。用 `WS_TICKET_STORE` 指定，留空跟随 `CACHE_STORE`。

| 驱动 | 结论 |
|---|---|
| `redis` | **推荐**：原生 TTL、无常驻长连接断开问题、支持多机部署（需 phpredis 扩展或 predis 包） |
| `file` | 单机可用，依赖共享磁盘，多机不行 |
| `database` | 能用但代价最高：每次建连一次 INSERT+DELETE 写放大，且常驻 Worker 持有 MySQL 长连接会被 `wait_timeout` 静默断开 |
| `array` / `null` | **不可用**：Worker 永远读不到票据，表现是「连上就断」且很难排查 |

`php artisan deploy:check` 会检查这一项：不共享的驱动**阻断**，database 降级为建议，指定 redis 却没有客户端实现也阻断。Worker 启动时同样会写一条 error 日志。

### 为什么不用 Cache::pull

一次性语义不用 `Cache::pull()` 实现。它的源码是 `tap(get(), fn => forget())`——**两条独立命令，且全部驱动（含 RedisStore、DatabaseStore）都没有覆写它**，并发下两个连接能拿到同一个 uid，「消费即失效」并不成立。

改用 `Cache::add()` 抢占位键：各驱动都以「只允许一个赢」的语义实现（Redis 走 Lua、database 靠 key 唯一索引的 `insertOrIgnore`、file 用 flock、memcached 用原生 add），抢不到的一律视为已兑现。取不到值的票据直接返回 null 且不写任何键，避免拿随机串反复请求把缓存灌满。

## 注意事项

- 端口：`1236`（Register，内部）、`2346`（Gateway，对外）、`2300+`（Gateway↔BusinessWorker 内部通信），防火墙只放开对外那一个。
- 水平扩展：多台服务器时所有 Gateway/BusinessWorker 指向同一个 `WS_REGISTER_ADDRESS` 即可。
