# 通用管理后台（General Admin）

基于 Laravel + Breeze（Blade/Tailwind CSS v4/Alpine.js）的现代化通用管理后台模板。
可快速复用：CRUD 极简、**RBAC 权限基于 spatie/laravel-permission**、现代化全宽 UI、项目级暗色模式、全中文界面。

## 技术栈

- Laravel 13（PHP 8.3）
- Laravel Breeze（Blade 版）：Tailwind CSS v4 + Alpine.js，无 Vue/React/Inertia/Livewire
- **MySQL**（主数据库）；测试使用 sqlite in-memory（可离线运行）
- **spatie/laravel-permission**：角色权限（roles / permissions / model_has_roles / model_has_permissions / role_has_permissions）
- **blade-ui-kit/blade-icons + blade-heroicons**：图标（`<x-icon name="heroicon-o-..." />`）
- **maatwebsite/excel**：Excel 导入导出（用户/文章）
- 未引入 Filament / Nova 等重型后台框架，业务模块可直接复制 Post 模板

## 功能清单

- 认证：用户名/邮箱登录 + **SVG 验证码**（点击刷新、一次性）、记住我
- 仪表盘：统计卡片 + 最近文章
- 用户管理：CRUD、角色分配、重置密码、软删除、**回收站（还原/彻底删除）**、**Excel 导入/导出**（导入：中文表头、姓名/邮箱查重含软删、失败行跳过不中断、密码留空自动生成随机强密码；导出跟随当前筛选条件）
- 角色管理：CRUD + **按菜单树分配权限**（目录 → 菜单 → 按钮）
- **菜单管理（菜单即权限）**：目录 / 菜单 / 按钮三级节点树 CRUD、启停、排序
- 文章管理：示例 CRUD 模板（分页/搜索/状态切换/软删除/**回收站**）+ **Excel 导出（跟随筛选）**
- **操作日志**：登录审计（成功/失败/登出）+ 后台写操作自动审计（谁/何时/做了什么/IP），**GET 导出也留痕**、业务异常也记录「执行失败」
- **系统设置**：站点名称 / 每页条数 / 版权信息（保存即全局生效，走缓存）
- **数据字典**：字典类型 + 字典项管理（名称/值/排序/状态/备注），业务侧用 `dict()` 读取（含缓存与自动失效）
- **中文错误页**：403 / 404 / 419 / 429 / 500 全中文 + 暗色模式适配
- RBAC 权限：菜单级 8 项 + 按钮级 15 项，全部由菜单树维护（见下）

## 菜单即权限（参考 BuildAdmin）

`menus` 表是一棵三类型节点的树，**同时承载侧边栏菜单与权限清单**：

| 类型 | 作用 | 参与鉴权 |
| --- | --- | --- |
| `dir` 目录 | 侧边栏分组标题（如「系统管理」） | 否 |
| `menu` 菜单 | 可导航页面，`permission_name` 决定菜单可见与页面可访问 | 是（菜单级） |
| `button` 按钮 | 页面内操作点（如「新增用户」），`@can` 控制显隐 + 后端 `Gate::authorize` 双重校验 | 是（按钮级） |

- 权限 = 菜单节点的 `permission_name`，保存菜单时**自动同步** `permissions` 表（中文名取菜单名称）
- 权限标识改名时：旧权限若未被任何角色使用则自动清理；已被授权则保留并提示
- 删除保护：有子节点、或权限已分配给角色 → 拒绝删除
- 授权入口唯一：**角色管理 → 权限分配**（按菜单树勾选，支持「全选本组」）
- 侧边栏由 `App\Support\Navigation` 依据 `menus` 表 + 用户权限动态生成，与页面可达性严格同源

### 权限清单（23 项）

| 层级 | 权限标识 |
| --- | --- |
| 菜单级 | `dashboard.view`、`post.manage`、`user.manage`、`role.manage`、`menu.manage`、`dict.manage`、`log.manage`、`settings.manage` |
| 按钮级 | `posts.create/update/destroy`、`users.create/update/destroy/reset-password/import/export`、`roles.create/update/destroy`、`menus.create/update/destroy` |

> 内置角色：`admin` 全部权限（`Gate::before` 短路放行）；`editor` = `dashboard.view` + `post.manage` + `posts.create/update/destroy`。

## 快速开始

```bash
# 1. 安装依赖（vendor 已在本机生成；换机器时执行）
composer install
npm install && npm run build

# 2. 配置环境
cp .env.example .env
php artisan key:generate

# 3. 配置数据库（本项目已配置 root / 123456 并建好库）
#    .env 中 DB_DATABASE=general_admin、DB_USERNAME=root、DB_PASSWORD=123456
#    如数据库不存在先创建：
mysql -uroot -p123456 -e "CREATE DATABASE general_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 4. 迁移 + 种子
php artisan migrate:fresh --seed

# 5. 启动
php artisan serve
# 访问 http://localhost:8000（公开欢迎页）
# 后台入口：http://localhost:8000/console/login
```

## 种子数据（`migrate:fresh --seed` 自动填充）

`DatabaseSeeder` 按依赖顺序调用 6 个 Seeder，全部**幂等**（按唯一键 `updateOrCreate` / `firstOrCreate`），可反复执行不会产生重复数据：

| 顺序 | Seeder | 填充内容 | 数据量 |
| --- | --- | --- | --- |
| 1 | `MenuPermissionSeeder` | 菜单树 + 同步 spatie 权限（**权限的唯一来源**） | 26 节点 / 23 权限 |
| 2 | `RolePermissionSeeder` | `admin`、`editor` 两个内置角色及授权 | 2 角色 |
| 3 | `UserSeeder` | admin / editor + 12 个测试用户并分配角色 | 14 用户 |
| 4 | `PostSeeder` | 20 篇示例文章（13 已发布 / 7 草稿，5 位作者） | 20 文章 |
| 5 | `DictSeeder` | 6 组数据字典类型 + 16 个字典项（含 1 组停用示例） | 6 类型 / 16 项 |
| 6 | `SettingSeeder` | 站点名称 / 每页条数 / 版权信息（已存在则不覆盖） | 3 项 |

字典内容：`post_status` 文章状态、`user_gender` 用户性别、`common_status` 通用状态、`audit_status` 审核状态、`notify_channel` 通知渠道、`demo_disabled` 停用演示。

> 只补种子不改结构：`php artisan db:seed`
> 需要重来一次：`php artisan migrate:fresh --seed`
> 单独重跑某一组：`php artisan db:seed --class=DictSeeder`

## 访问路径（可配置）

- 根路径 `/`：公开欢迎页（不跳登录）
- **后台前缀可配置**：`.env` 中的 `APP_ADMIN_PREFIX`（默认 `console`，避免常见的 admin），修改后所有后台路由随之变化
- 后台入口：`/{APP_ADMIN_PREFIX}/login`（默认 `/console/login`，**用户名或邮箱**均可登录）
- 后台页面统一挂载：`/{APP_ADMIN_PREFIX}/dashboard`、`/users`、`/roles`、`/menus`、`/posts` 等（默认 `/console/...`）
- 未登录访问后台任意页 → 自动跳转 `/{APP_ADMIN_PREFIX}/login`

> 升级已有环境：新增「菜单管理」后需要执行一次 `php artisan migrate` + `php artisan db:seed --class=MenuPermissionSeeder`，
> 否则侧边栏（由菜单表驱动）会是空的。全新安装用 `migrate:fresh --seed` 即可。

## 内置账号（Seeder）

| 账号 | 密码 | 登录方式 | 角色 | 权限 |
| --- | --- | --- | --- | --- |
| 管理员 / admin@example.com | password | 用户名或邮箱 | 超级管理员（admin） | 全部 23 项权限 |
| 编辑 / editor@example.com | password | 用户名或邮箱 | 编辑（editor） | dashboard.view、post.manage、posts.create/update/destroy |
| 测试用户1 ~ 测试用户12 | password | 用户名 | user1=admin，其余 editor（固定规则） | 同上 |

> 登录标识支持「用户名」（users.name，唯一）或「邮箱」两种方式，自动识别。
> 种子顺序：`MenuPermissionSeeder`（菜单树 → 权限）→ `RolePermissionSeeder`（角色授权）→ `UserSeeder` → `PostSeeder` → `DictSeeder` → `SettingSeeder`。

## 数据字典读取（dict()）

字典数据入库后，业务侧通过全局辅助函数读取（`app/Support/helpers.php` + `App\Support\Dict`）：

```php
dict('post_status');                    // 全部启用项：[draft => 草稿, published => 已发布, ...]
dict('post_status', 'draft');           // 单项中文名：'草稿'
dict('post_status', 'no-such', '未知'); // 带默认值：'未知'
```

- 读取结果缓存到 `dict.{type}` 键（永久缓存），字典增删改时由控制器自动失效，新值立即可见
- **类型停用 = 整组字典不可读**；类型内单个停用项也不返回
- 视图里可直接用：`{{ dict('post_status', $post->status) }}`

## 时区与缓存

- **时区**：默认东八区（`APP_TIMEZONE=Asia/Shanghai`，`.env` 可覆盖）。系统生成时间（操作日志、created_at）与表单手工填写的本地时间统一按东八区解释，不再出现「少 8 小时」或同列两种时间基准
- **权限判定**：`Gate::before` 走 Spatie 权限缓存（默认 24 小时），权限/角色变更时自动失效，不再每次 `@can` 都查表
- **系统设置**：`settings` 表查询缓存到 `app.settings` 键，保存设置时立即失效

## RBAC 设计（spatie/laravel-permission + 菜单驱动）

- 权限清单由 `menus` 表维护（菜单即权限），落库到 Spatie 的 `permissions` 表
- `admin` 角色拥有全部权限（`Gate::before` 短路）
- `AuthServiceProvider` 统一注册 Gate：`$user->can('user.manage')` 与 Blade `@can('user.manage')` 直接可用，权限在菜单管理新建后立即可用；**权限判定走 Spatie 缓存**，变更自动失效
- 中间件（Spatie 自带）按菜单级权限保护路由：
  - `permission:user.manage` → 用户管理
  - `permission:role.manage` → 角色管理（与侧边栏判定同源，不再出现「看得到点不开」）
  - `permission:menu.manage` → 菜单管理
  - `permission:settings.manage` → 系统设置
- 按钮级权限双重校验：视图 `@can('users.create')` 控制显隐，控制器 `Gate::authorize('users.create')` 拦截越权请求（用户模块已全量接入，其余模块可照抄）
- 视图层通过 `@can` 控制侧边栏菜单与按钮显隐
- 角色/权限表扩展字段：`roles.description`（角色描述）、`permissions.label`（权限中文名，来自菜单名称）

## 路由与权限

后台统一挂载在**可配置前缀**下（默认 `console`，由 `.env` 的 `APP_ADMIN_PREFIX` 控制；路由名不变，URL 带前缀）：

| 路由 | 控制器 | 权限保护 |
| --- | --- | --- |
| GET /{prefix}/dashboard | DashboardController | permission:dashboard.view |
| resource {prefix}/users（不含 show）+ POST {prefix}/users/{user}/reset-password + 导入/导出 + **回收站（trash/restore/force-delete）** | UserController | permission:user.manage（按钮级另由 `users.*` 权限二次校验） |
| resource {prefix}/roles（不含 show） | RoleController | permission:role.manage |
| resource {prefix}/menus（不含 show）+ PATCH {prefix}/menus/{menu}/toggle-status | MenuController | permission:menu.manage |
| resource {prefix}/posts（不含 show）+ PATCH {prefix}/posts/{post}/toggle-status + **回收站（trash/restore/force-delete）** | PostController | permission:post.manage |
| GET /{prefix}/logs | OperationLogController | permission:log.manage |
| GET\|PUT /{prefix}/settings | SettingController | permission:settings.manage |
| resource {prefix}/dict-types、{prefix}/dict-items（不含 show） | DictTypeController / DictItemController | permission:dict.manage |
| /{prefix}/login 等 | Breeze 默认 | guest |
| /{prefix}/profile、/{prefix}/logout 等 | Breeze 默认 | auth |

## 数据库表结构

- `users`：id、name、email（唯一）、email_verified_at、password、remember_token、deleted_at（软删除）、timestamps
- `roles`：id、name（唯一，角色标识）、description、guard_name、timestamps
- `permissions`：id、name（唯一，权限标识）、label（中文名）、description、guard_name、timestamps
- `model_has_roles` / `model_has_permissions` / `role_has_permissions`：Spatie 关联表
- `posts`：id、user_id、title、content、status（draft/published）、published_at、deleted_at（软删除）、timestamps

## 暗色模式

项目级 Tailwind `dark:` 类切换（不依赖 `prefers-color-scheme`）：

- Tailwind v4 CSS 中通过 `@custom-variant dark (&:where(.dark, .dark *));` 将 `dark:` 绑定到 `<html class="dark">`
- 顶栏提供明/暗切换按钮，写入 `localStorage.theme`
- 默认跟随系统（`prefers-color-scheme`），手动切换后以手动选择为准
- `<head>` 内联脚本在 CSS 加载前设置 `.dark` 类，避免闪烁

## UI 设计

- 全宽布局（无 `mx-auto max-w-*` 居中容器）：深色侧边栏 + 浅色内容卡片
- 侧边栏由 `menus` 表动态生成（分组 / 图标 / 排序 / 激活高亮全部可后台配置），权限控制显隐
- 统计卡片、数据表格（悬停、状态徽章）、统一表单样式、flash 提示条
- 图标：blade-heroicons（Heroicons 2.0，`<x-icon name="heroicon-o-*" />`），菜单图标可在「菜单管理」中配置
- 通用组件类集中在 `resources/css/app.css`（card / btn-* / input / th / td / stat-card）

## 文章模块 = 业务 CRUD 模板

`Post`（文章）是示例 CRUD 模板：列表（分页+关键字搜索+状态筛选）、创建、编辑、删除（软删除）、状态切换、**回收站（还原/彻底删除）**、导出（跟随筛选）。
复制以下文件即可生成新业务模块：

- 迁移：`database/migrations/2026_09_12_000002_create_posts_table.php`
- 模型：`app/Models/Post.php`（含搜索/状态作用域）
- 控制器：`app/Http/Controllers/PostController.php`
- 请求校验：`app/Http/Requests/StorePostRequest.php`、`UpdatePostRequest.php`
- 视图：`resources/views/posts/{index,create,edit}.blade.php`

**新增模块后别忘了在「菜单管理」里加节点**：新建菜单节点（填权限标识，如 `report.view`）→ 权限自动入库 → 侧边栏与角色授权页立即生效，无需改代码。

## 认证

- 登录标识：**用户名或邮箱**（自动识别，`app/Http/Requests/Auth/LoginRequest.php`）
- 用户名唯一（`users.name` 唯一索引），创建/编辑用户时校验
- **后台不开放注册、不提供忘记密码自助找回**：注册与忘记密码/重置密码路由已移除（访问均返回 404），账号密码由管理员在「用户管理」中创建 / 重置
- 登录页为现代化分屏设计（品牌区 + 表单卡片，支持密码显示/隐藏切换），暗色模式自动跟随系统，可用 `?theme=light|dark` 强制预览

## 测试

测试使用 sqlite 内存库，无需 MySQL，可离线运行：

```bash
php artisan test
# 165 passed (532 assertions)
```

覆盖：认证（Breeze 默认）、仪表盘、用户管理、**用户导入（中文表头、重名/软删查重、角色与密码校验、失败行不中断、随机密码）**、角色管理、菜单管理（菜单即权限：权限自动同步 / 删除保护 / 改名清理）、文章管理、**数据字典读取 dict()（缓存与失效）**、**操作日志审计（GET 导出留痕）**、**回收站全链路（软删 → 回收站 → 还原 → 彻底删除）**、**中文错误页（403/404/419/429/500）**、**导出跟随筛选（FromQuery 流式）**、**种子数据完整性（幂等、权限与菜单双向一致、后台页面可渲染）**、RBAC 权限控制（含 QA 冒烟：直接 POST 越权拦截、侧边栏与页面可达性同源、权限保存端到端生效）。
