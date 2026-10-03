# 健康检查与监控

本项目提供两个健康检查端点，用于负载均衡探活与运维自检。

| 端点 | 认证 | 用途 |
|---|---|---|
| `GET /health` | **无需登录** | 负载均衡 / 容器探活。只返回应用存活与版本信息，不触碰数据库 |
| `GET /health/detailed` | 内网 IP 或 `?token=` | 深度自检，逐项检测 7 个子系统 |

另有 Laravel 自带的 `GET /up`（框架内置，仅验证启动）。

## 1. 基础端点 `/health`

```bash
curl https://admin.example.com/health
```

```json
{
  "status": "ok",
  "app": "通用管理后台",
  "env": "production",
  "version": "1.0.0",
  "php": "8.3.33",
  "timestamp": "2026-10-03T21:40:00+08:00"
}
```

- 始终返回 `200`（除非应用根本没起来）
- **不查数据库**——避免数据库抖动时把整个集群摘掉（探活端点连 DB 是常见误设计）
- 可直接配到 Nginx / 负载均衡 / K8s liveness probe

## 2. 深度端点 `/health/detailed`

```bash
curl https://admin.example.com/health/detailed
curl https://admin.example.com/health/detailed?token=你的HEALTH_TOKEN
```

访问控制（满足其一即可）：

1. 来源为内网 IP：`127.0.0.1`、`10.0.0.0/8`、`172.16.0.0/12`、`192.168.0.0/16`、`169.254.0.0/16`（含 Docker 默认网桥）
2. 携带正确 `?token=`（`.env` 的 `HEALTH_TOKEN`）

不满足则返回 `403`。限流 `30 次/分钟`。

### 检测项

| key | 检测内容 | 异常判定 |
|---|---|---|
| `database` | DB 连通性（一次轻量查询） | 连不上 → `error` |
| `migrations` | 是否有未执行的迁移 | 有 pending → `degraded` |
| `cache` | 缓存写入 + 读取 + 删除（探针键，用完即删） | 读写失败 → `error` |
| `backup` | 最新备份的新鲜度（对接 `spatie/laravel-backup`） | 无备份或超期 → `degraded` |
| `storage` | 关键目录是否可写 | 只读 → `error` |
| `disk` | 磁盘剩余空间 | 低于 `HEALTH_DISK_FREE_MIN_MB`（默认 500MB）→ `degraded` |
| `queue` | 队列挂起数与失败数（`database` 驱动时才深探测） | 挂起超 `HEALTH_QUEUE_PENDING_MAX`（默认 50）→ `degraded` |

响应示例：

```json
{
  "status": "degraded",
  "checks": {
    "database":   { "status": "ok",       "message": "数据库连接正常", "meta": { "connection": "mysql" } },
    "migrations": { "status": "ok",       "message": "迁移已全部执行", "meta": { "pending": 0 } },
    "cache":      { "status": "ok",       "message": "缓存读写正常", "meta": { "store": "database" } },
    "backup":     { "status": "degraded", "message": "最新备份距今 3.2 天", "meta": { "age_days": 3.2, "max_age_days": 1 } },
    "storage":    { "status": "ok",       "message": "目录均可写", "meta": {} },
    "disk":       { "status": "ok",       "message": "剩余 128.4 GB", "meta": { "free_mb": 131481 } },
    "queue":      { "status": "ok",       "message": "挂起 0 条 / 失败 0 条（阈值 50）", "meta": { "connection": "database", "pending": 0, "failed": 0 } }
  }
}
```

- 顶层 `status` 取所有检查项中最差的一个：`ok` > `degraded` > `error`
- HTTP 状态码仍为 `200`（便于监控直接解析 JSON）；**是否告警由监控系统根据 `status` 判断**，不要只看 HTTP 码

## 3. 阈值配置

```dotenv
HEALTH_TOKEN=                    # 留空＝只放行内网
HEALTH_DISK_FREE_MIN_MB=500
HEALTH_QUEUE_PENDING_MAX=50
```

## 4. 建议的监控接入

- **探活**：`GET /health`，5 秒间隔，连续 3 次失败则告警
- **深度**：`GET /health/detailed?token=...`，10 分钟一次；`status != ok` 即告警
- 与上一节的备份告警形成互补：`backup:monitor` 只看备份本身，`/health/detailed` 把备份纳入整体健康视图

## 5. 安全说明

- `HEALTH_TOKEN` 不入库，泄露后立即在 `.env` 轮换
- 深度端点暴露了内部信息（迁移数、队列积压、磁盘余量），**不要对外网开放**
- 反向代理场景下需正确传递 `X-Forwarded-For`，否则内网判定会失效（详见部署清单）
