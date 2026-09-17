<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

/**
 * 认证/授权服务提供者
 *
 * RBAC Gate 统一通过 Gate::before 短路判定（基于 spatie/laravel-permission）：
 * - admin 角色拥有全部权限（返回 true）；
 * - 权限表中已定义的权限 name，按 用户-角色-权限 链判定（返回 true/false）；
 * - 其它能力（策略等）返回 null，交由后续 Gate/Policy 判定。
 *
 * 采用 Gate::before 而非逐个 Gate::define 注册的好处：
 * 权限在运行时（Seeder / 后台）创建后立即可用，不依赖服务启动时权限表状态，
 * 且 `$user->can('user.manage')` 与 Blade `@can('user.manage')` 可直接使用。
 *
 * 性能：权限清单取自 Spatie 的 PermissionRegistrar 缓存（默认缓存 24 小时，
 * 权限/角色变更时由 Spatie 自动失效），不再每次能力判定都查 permissions 表。
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            // admin 角色拥有全部权限
            if ($user->hasRole(\App\Models\User::ROLE_ADMIN)) {
                return true;
            }

            // 该能力是否为权限表中已定义的权限（走 Spatie 缓存集合，避免每请求查表）
            $permissions = app(PermissionRegistrar::class)->getPermissions();

            if ($permissions->contains('name', $ability)) {
                return $user->hasPermissionTo($ability);
            }

            // 非 RBAC 权限能力，交回 Gate/Policy 处理
            return null;
        });
    }
}
