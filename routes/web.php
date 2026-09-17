<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DictItemController;
use App\Http\Controllers\DictTypeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OperationLogController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
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
        // 回收站（须在 resource 之前注册，避免被 {user} 参数捕获）
        Route::get('users/trash', [UserController::class, 'trash'])->name('users.trash');
        Route::patch('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{user}/force-delete', [UserController::class, 'forceDestroy'])->name('users.force-destroy');

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

    // 操作日志：需要 log.manage 权限
    Route::middleware('permission:log.manage')->group(function () {
        Route::get('logs', [OperationLogController::class, 'index'])->name('logs.index');
    });

    // 系统设置：需要 settings.manage 权限
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });

    // 数据字典：需要 dict.manage 权限
    Route::middleware('permission:dict.manage')->group(function () {
        Route::resource('dict-types', DictTypeController::class)->except(['show']);
        Route::resource('dict-items', DictItemController::class)->except(['show']);
    });

    // 文章管理：需要 post.manage 权限（示例 CRUD 模板）
    Route::middleware('permission:post.manage')->group(function () {
        // 回收站（须在 resource 之前注册）
        Route::get('posts/trash', [PostController::class, 'trash'])->name('posts.trash');
        Route::patch('posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore');
        Route::delete('posts/{post}/force-delete', [PostController::class, 'forceDestroy'])->name('posts.force-destroy');

        Route::resource('posts', PostController::class)->except(['show']);
        Route::patch('posts/{post}/toggle-status', [PostController::class, 'toggleStatus'])
            ->name('posts.toggle-status');
        // Excel 导出
        Route::get('posts/export', [PostController::class, 'export'])->name('posts.export');
    });

    // 个人资料
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
