# API 层规划（docs）

状态：规划稿（未实施）
日期：2026-09-25
背景：当前为纯 Web 后台（Blade SSR + Vue 挂载，props 桥接，无任何 JSON 接口）

---

## 一、要不要做（先判断，再动手）

| 触发信号 | 建议 |
|---|---|
| 短期无多端/小程序/第三方集成需求 | **维持现状**，本规划存档备用 |
| 需要移动端 / 微信小程序 / 外部系统对接 | 按本规划分阶段实施 |
| 仅需"给某个页面补接口" | 不需要 API 层，直接在控制器加 JSON 响应即可 |

> 结论先行：**这是纯管理后台，API 层非必需**。建议先只做「原则沉淀」，实施等真实需求出现。

---

## 二、总体架构

```
前端(Web/Vue) ── Blade SSR ──┐
                             ├── Web 控制器（现状，保留）
小程序/App/第三方 ── JSON ──┴── API 控制器（新增）── 共用逻辑层
                                              │
                                        FormRequest（复用校验）
                                        Services / Actions（抽取共用）
                                        RBAC（spatie，直接复用）
```

**核心原则：Web 与 API 共用「校验 + 业务逻辑」，不各写一套。**

---

## 三、认证方案（P0）

- 装 **Laravel Sanctum**（官方，轻量，适配现有 `users` 表 + 登录用 username/email）
- `routes/api.php` 全部挂 `/api/v1` 前缀 + `auth:sanctum` 中间件
- 复用现有 RBAC：API 控制器里 `$request->user()->can('user.manage')` 直接生效（`Gate::before` 已就绪）
- 停用账号 / 首登改密逻辑：`EnsurePasswordChanged` 中间件同样挂在 API 组
- Token 签发：`POST /api/v1/auth/token`（username + password + 可选 captcha？—— **不建议**：API 场景验证码不适用，改用限流兜底）

**限流（重要）**：API 登录端点必须配 `throttle`（如 `5/min`），与 Web 登录限流（纯 IP，已加固）一致。

---

## 四、路由与资源（P1：只读 → P2：写）

```
GET  /api/v1/me                       当前用户（含 roles/permissions）
GET  /api/v1/users                    用户列表（复用 ListQuery 白名单分页）
GET  /api/v1/users/{user}
GET  /api/v1/roles / /api/v1/menus    权限/菜单树（前端动态路由友好）
GET  /api/v1/dict-types / {type}/items 字典（含 dict() 语义）
GET  /api/v1/posts                    文章列表
POST/PATCH/DELETE ...                 P2 阶段按需开放写操作
```

- 返回统一结构：`{ "data": ..., "meta": { "pagination": {...} } }`
- 错误统一：`{ "message": "...", "errors": {...} }`（422 校验错误沿用 Laravel 格式）
- 版本化：URL `/api/v1`；破坏性变更升 `/api/v2`，不删旧版

---

## 五、逻辑复用（关键，避免双实现）

| 现有资产 | API 侧复用方式 |
|---|---|
| `ListQuery::resolve()` | 直接复用（白名单分页天然防注入） |
| `Store/Update*Request` | 直接复用（校验规则同一份，Web/API 行为一致） |
| `PostPolicy` / `isLastActiveAdmin` | 直接复用（数据范围与保护规则不重写） |
| `OperationLogger` | API 写操作同样调用（审计不丢） |
| `Dict` helper | 复用（字典语义一致） |
| 控制器查询逻辑 | **抽取到 Actions/Services**（如 `ListUsers`、`CreateUser`），Web 控制器与 API 控制器都调用——这是唯一需要动手的重构，建议按模块渐进抽取，不动现有行为 |

---

## 六、安全清单

- [ ] Sanctum token 过期策略（短期 token + 按需 refresh；后台场景可给长 token 但加入口）
- [ ] `throttle` 中间件挂在全部 `/api/*`（默认 `60/min`，登录端点 `5/min`）
- [ ] CORS：`config/cors.php` 白名单（仅放行已知域名；后台无跨域需求可保持默认收紧）
- [ ] API 响应不泄露：User 模型已 `$hidden` password/remember_token，API 用 Resource 再显式挑字段
- [ ] 权限断言：所有 API 控制器入口 `Gate::authorize`（复用 Web 侧粒度）
- [ ] 审计：写操作记录 `OperationLogger`（module 标注「API」）

---

## 七、测试策略

- `tests/Feature/Api/` 独立目录，`RefreshDatabase` + `Sanctum::actingAs()`
- 必测：token 签发/过期、401 无 token、403 权限不足、ListQuery 白名单注入、写操作审计留痕
- CI 无需新增步骤（`php artisan test` 自动覆盖）

---

## 八、实施路径（按需触发）

| 阶段 | 内容 | 工作量 |
|---|---|---|
| P0 | 装 Sanctum + `/api/v1/auth/token` + 限流 + me 接口 | 小（0.5 天） |
| P1 | 只读资源（users/roles/menus/dict/posts）+ Resource 转换 | 中（1-2 天） |
| P2 | 写操作（按模块开放）+ Actions 抽取 + 审计 | 中（2-3 天，渐进） |

---

## 九、结论

**当前不建议实施**：纯后台无多端需求，API 层是"锦上添花"且引入 token 管理、CORS、双入口维护成本。本规划存档；当出现第一个真实对接需求时，按 P0 → P1 顺序执行即可（每个阶段独立可交付、可回滚）。
