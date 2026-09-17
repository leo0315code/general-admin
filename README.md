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
- 用户管理：CRUD、角色分配、重置密码、软删除、**Excel 导入/导出**
- 角色管理：CRUD + 权限分配（Spatie）
- 文章管理：示例 CRUD 模板（分页/搜索/状态切换/软删除）+ **Excel 导出**
- **操作日志**：登录审计（成功/失败/登出）+ 后台写操作自动审计（谁/何时/做了什么/IP）
- **系统设置**：站点名称 / 每页条数 / 版权信息（保存即全局生效）
- **数据字典**：字典类型 + 字典项管理（名称/值/排序/状态/备注）
- RBAC 权限：`dashboard.view` / `user.manage` / `post.manage` / `role.manage` / `log.manage` / `dict.manage`

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

## 访问路径（可配置）

- 根路径 `/`：公开欢迎页（不跳登录）
- **后台前缀可配置**：`.env` 中的 `APP_ADMIN_PREFIX`（默认 `console`，避免常见的 admin），修改后所有后台路由随之变化
- 后台入口：`/{APP_ADMIN_PREFIX}/login`（默认 `/console/login`，**用户名或邮箱**均可登录）
- 后台页面统一挂载：`/{APP_ADMIN_PREFIX}/dashboard`、`/users`、`/roles`、`/posts`（默认 `/console/...`）
- 未登录访问后台任意页 → 自动跳转 `/{APP_ADMIN_PREFIX}/login`

## 内置账号（Seeder）

| 账号 | 密码 | 登录方式 | 角色 | 权限 |
| --- | --- | --- | --- | --- |
| 管理员 / admin@example.com | password | 用户名或邮箱 | 超级管理员（admin） | 全部权限 |
| 编辑 / editor@example.com | password | 用户名或邮箱 | 编辑（editor） | dashboard.view、post.manage |
| 测试用户1 ~ 测试用户12 | password | 用户名 | admin / editor 随机 | 同上 |

> 登录标识支持「用户名」（users.name，唯一）或「邮箱」两种方式，自动识别。

## RBAC 设计（spatie/laravel-permission）

- 权限（name 即权限标识，label 为中文展示名）：`dashboard.view`、`user.manage`、`post.manage`、`role.manage`
- `admin` 角色拥有全部权限（`Gate::before` 短路）
- `AuthServiceProvider` 统一注册 Gate：`$user->can('user.manage')` 与 Blade `@can('user.manage')` 直接可用，权限在后台新建后立即可用
- 中间件（Spatie 自带）：
  - `permission:user.manage` → 权限中间件
  - `role:admin` → 角色中间件（角色管理路由）
- 视图层通过 `@can` 控制侧边栏菜单与按钮显隐
- 角色/权限表已扩展字段：`roles.description`（角色描述）、`permissions.label`（权限中文名）

## 路由与权限

后台统一挂载在**可配置前缀**下（默认 `console`，由 `.env` 的 `APP_ADMIN_PREFIX` 控制；路由名不变，URL 带前缀）：

| 路由 | 控制器 | 权限保护 |
| --- | --- | --- |
| GET /{prefix}/dashboard | DashboardController | permission:dashboard.view |
| resource {prefix}/users（不含 show）+ POST {prefix}/users/{user}/reset-password | UserController | permission:user.manage |
| resource {prefix}/roles（不含 show） | RoleController | role:admin（Spatie 角色中间件） |
| resource {prefix}/posts（不含 show）+ PATCH {prefix}/posts/{post}/toggle-status | PostController | permission:post.manage |
| /{prefix}/login、/{prefix}/register、/{prefix}/forgot-password 等 | Breeze 默认 | guest |
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
- 侧边栏分组菜单（概览 / 内容管理 / 系统管理），权限控制显隐，激活项高亮
- 统计卡片、数据表格（悬停、状态徽章）、统一表单样式、flash 提示条
- 图标：blade-heroicons（Heroicons 2.0，`<x-icon name="heroicon-o-*" />`）
- 通用组件类集中在 `resources/css/app.css`（card / btn-* / input / th / td / stat-card）

## 文章模块 = 业务 CRUD 模板

`Post`（文章）是示例 CRUD 模板：列表（分页+关键字搜索+状态筛选）、创建、编辑、删除（软删除）、状态切换。
复制以下文件即可生成新业务模块：

- 迁移：`database/migrations/2026_09_12_000002_create_posts_table.php`
- 模型：`app/Models/Post.php`（含搜索/状态作用域）
- 控制器：`app/Http/Controllers/PostController.php`
- 请求校验：`app/Http/Requests/StorePostRequest.php`、`UpdatePostRequest.php`
- 视图：`resources/views/posts/{index,create,edit}.blade.php`

## 认证

- 登录标识：**用户名或邮箱**（自动识别，`app/Http/Requests/Auth/LoginRequest.php`）
- 用户名唯一（`users.name` 唯一索引），创建/编辑用户时校验
- **后台不开放注册、不提供忘记密码自助找回**：注册与忘记密码/重置密码路由已移除（访问均返回 404），账号密码由管理员在「用户管理」中创建 / 重置
- 登录页为现代化分屏设计（品牌区 + 表单卡片，支持密码显示/隐藏切换），暗色模式自动跟随系统，可用 `?theme=light|dark` 强制预览

## 测试

测试使用 sqlite 内存库，无需 MySQL，可离线运行：

```bash
php artisan test
# 94 passed (266 assertions)
```

覆盖：认证（Breeze 默认）、仪表盘、用户管理、角色管理、文章管理、RBAC 权限控制（含 QA 冒烟：直接 POST 越权拦截、侧边栏菜单显隐、权限保存端到端生效）。
