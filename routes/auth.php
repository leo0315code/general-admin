<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// 后台认证路由统一挂载在可配置前缀下（默认 console，见 config/app.php 的 admin_prefix）
// 注意：后台管理面板不开放注册、不提供忘记密码自助找回
// （账号与密码由管理员在「用户管理」中创建 / 重置）
Route::prefix(config('app.admin_prefix'))->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])
            ->name('login');

        Route::post('login', [AuthenticatedSessionController::class, 'store']);

        // 登录验证码（SVG 图片，点击可刷新）
        Route::get('captcha', [AuthenticatedSessionController::class, 'captcha'])
            ->name('captcha');
    });

    Route::middleware('auth')->group(function () {
        Route::get('verify-email', EmailVerificationPromptController::class)
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
            ->name('password.confirm');

        Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

        Route::put('password', [PasswordController::class, 'update'])->name('password.update');

        // 首次登录强制改密（登录成功时 must_change_password=1 会跳转到这里）
        Route::get('password-setup', [PasswordSetupController::class, 'show'])
            ->name('password.setup');
        Route::post('password-setup', [PasswordSetupController::class, 'update'])
            ->name('password.setup.update');

        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');
    });
});
