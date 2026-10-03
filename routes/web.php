<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DictItemController;
use App\Http\Controllers\DictTypeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MessageBroadcastController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationLogController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WsTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web 路由
|--------------------------------------------------------------------------
|
| 后台全部挂载在可配置前缀下（默认 console，见 config/app.php 的 admin_prefix；
| 通过 .env 的 APP_ADMIN_PREFIX 自定义，避免常见的 admin）。
| 根路径 / 为公开欢迎页，不会自动跳转到登录页。
| 认证相关路由由 Breeze 提供（routes/auth.php，同样挂载在可配置前缀下）。
|
*/

$adminPrefix = config('app.admin_prefix');

// 根路径：公开欢迎页（登录用户直接进入后台）
Route::get('/', fn () => view('welcome'));

// 后台业务路由（可配置前缀），按权限分组保护。
// 权限与侧边栏菜单同源（menus 表，「菜单即权限」），当前菜单级权限：
// - dashboard.view   仪表盘        - user.manage      用户管理
// - post.manage      文章管理      - role.manage      角色管理
// - menu.manage      菜单管理      - log.manage       操作日志
// - dict.manage      数据字典      - settings.manage  系统设置
// 页面内的按钮级权限（如 users.create / posts.destroy）同样来自 menus 表的 button 节点。
Route::prefix($adminPrefix)->middleware(['auth', 'verified', 'password.changed'])->group(function () {
    // 仪表盘：需要 dashboard.view 权限
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // 用户管理：需要 user.manage 权限
    Route::middleware('permission:user.manage')->group(function () {
        // 用户搜索（供「发送消息」选人；须在 resource 之前注册）
        Route::get('users/search', [UserController::class, 'search'])->name('users.search');

        // 回收站（须在 resource 之前注册，避免被 {user} 参数捕获）
        Route::get('users/trash', [UserController::class, 'trash'])->name('users.trash');
        Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{user}/force-delete', [UserController::class, 'forceDestroy'])->name('users.force-destroy');

        // 批量操作（同样须在 resource 之前注册，避免被 {user} 参数捕获）
        Route::post('users/bulk-delete', [UserController::class, 'bulkDestroy'])->name('users.bulk-delete');
        Route::post('users/bulk-toggle-status', [UserController::class, 'bulkToggleStatus'])->name('users.bulk-toggle-status');

        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
        // 账号启停
        Route::patch('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status');
        // Excel 导入导出
        Route::get('users/export', [UserController::class, 'export'])->name('users.export');
        Route::get('users/import-template', [UserController::class, 'importTemplate'])->name('users.import-template');
        Route::post('users/import', [UserController::class, 'import'])->name('users.import');
        Route::get('users/import-errors/{token}', [UserController::class, 'downloadImportErrors'])
            ->name('users.import-errors');
    });

    // 角色管理：需要 role.manage 权限（与侧边栏菜单判定同源，避免"看得到点不开"）
    Route::middleware('permission:role.manage')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    // 菜单管理（菜单即权限）：需要 menu.manage 权限
    Route::middleware('permission:menu.manage')->group(function () {
        Route::resource('menus', MenuController::class)->except(['show']);
        Route::patch('menus/{menu}/toggle-status', [MenuController::class, 'toggleStatus'])
            ->name('menus.toggle-status');
    });

    // 操作日志：需要 log.manage 权限（详情跟随菜单权限；导出需按钮权限 log.export）
    Route::middleware('permission:log.manage')->group(function () {
        // 静态路径须先于 {log} 注册，避免被参数捕获
        Route::get('logs/export', [OperationLogController::class, 'export'])->name('logs.export');
        Route::get('logs', [OperationLogController::class, 'index'])->name('logs.index');
        Route::get('logs/{log}', [OperationLogController::class, 'show'])->name('logs.show');
    });

    // 系统设置：需要 settings.manage 权限
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // 附件管理（上传基座）：需要 attachments.manage 权限
    Route::middleware('permission:attachments.manage')->group(function () {
        Route::get('attachments', [AttachmentController::class, 'index'])->name('attachments.index');
        Route::post('attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        // 下载须在 {attachment} 之前注册，避免被参数捕获
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])
            ->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])
            ->name('attachments.destroy');
    });

    // 数据字典：需要 dict.manage 权限
    Route::middleware('permission:dict.manage')->group(function () {
        Route::resource('dict-types', DictTypeController::class)->except(['show']);
        Route::resource('dict-items', DictItemController::class)->except(['show']);
    });

    // 文章管理：需要 post.manage 权限（示例 CRUD 模板）
    Route::middleware('permission:post.manage')->group(function () {
        // 回收站（须在 resource 之前注册）
        // 文章封面（附件基座的业务接入）：上传/预览须在 resource 之前注册
        Route::post('posts/cover-upload', [PostController::class, 'coverUpload'])->name('posts.cover-upload');
        Route::get('posts/cover-preview/{attachment}', [PostController::class, 'coverPreview'])->name('posts.cover-preview');
        Route::get('posts/trash', [PostController::class, 'trash'])->name('posts.trash');
        Route::patch('posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore');
        Route::delete('posts/{post}/force-delete', [PostController::class, 'forceDestroy'])->name('posts.force-destroy');

        // 批量操作（同样须在 resource 之前注册）
        Route::post('posts/bulk-delete', [PostController::class, 'bulkDestroy'])->name('posts.bulk-delete');

        Route::resource('posts', PostController::class)->except(['show']);
        Route::patch('posts/{post}/toggle-status', [PostController::class, 'toggleStatus'])
            ->name('posts.toggle-status');
        // Excel 导出
        Route::get('posts/export', [PostController::class, 'export'])->name('posts.export');
    });

    // WebSocket 连接票据（一次性，供通知铃铛组件建连后绑定 uid）
    Route::post('ws/ticket', [WsTicketController::class, 'issue'])->name('ws.ticket');

    // 通知中心（个人数据，不挂菜单级权限：越权访问他人通知由 NotificationPolicy 拦）
    // 静态路径须先于 {notification} 注册，避免被参数捕获
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy');

    // 主动发送消息（群发站内通知）：看历史 / 发送 / 撤回 三级权限
    Route::middleware('permission:messages.manage')->group(function () {
        Route::get('messages', [MessageBroadcastController::class, 'index'])->name('messages.index');
        Route::get('messages/create', [MessageBroadcastController::class, 'create'])
            ->middleware('permission:messages.create')
            ->name('messages.create');
        Route::post('messages', [MessageBroadcastController::class, 'store'])
            ->middleware('permission:messages.create')
            ->name('messages.store');
        Route::delete('messages/{broadcast}/revoke', [MessageBroadcastController::class, 'revoke'])
            ->middleware('permission:messages.revoke')
            ->name('messages.revoke');
    });

    // 个人资料
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
