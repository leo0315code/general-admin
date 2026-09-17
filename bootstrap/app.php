<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 后台挂载在可配置前缀下（默认 console）：未认证访问后台跳转到后台登录页
        // 使用闭包延迟解析 config（bootstrap 阶段 config helper 尚未加载）
        $middleware->redirectGuestsTo(
            fn () => '/'.config('app.admin_prefix').'/login'
        );

        // 操作日志中间件（记录后台写操作）
        $middleware->web(append: [
            \App\Http\Middleware\LogOperation::class,
            // 安全响应头（UI 现代化重构 · T05 / SEC-4）
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Spatie laravel-permission 提供的中间件别名
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // 首次登录强制改密
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
