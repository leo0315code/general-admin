<?php

namespace App\Support;

use App\Models\OperationLog;
use Illuminate\Http\Request;

/**
 * 操作日志记录器
 *
 * 统一写库入口 + 路由名 → 中文（模块/操作/描述）映射。
 * 登录成功/失败/登出由控制器手动记录；后台增删改由中间件 LogOperation 自动记录。
 */
class OperationLogger
{
    /** 路由名 → [模块, 操作, 描述] 映射 */
    private const ROUTE_MAP = [
        // 用户管理
        'users.store' => ['用户', '创建', '创建用户'],
        'users.update' => ['用户', '修改', '更新用户'],
        'users.destroy' => ['用户', '删除', '删除用户'],
        'users.reset-password' => ['用户', '修改', '重置用户密码'],
        'users.import' => ['用户', '导入', '批量导入用户'],
        'users.export' => ['用户', '导出', '导出用户数据'],
        // 角色管理
        'roles.store' => ['角色', '创建', '创建角色'],
        'roles.update' => ['角色', '修改', '更新角色'],
        'roles.destroy' => ['角色', '删除', '删除角色'],
        // 文章管理
        'posts.store' => ['文章', '创建', '创建文章'],
        'posts.update' => ['文章', '修改', '更新文章'],
        'posts.destroy' => ['文章', '删除', '删除文章'],
        'posts.toggle-status' => ['文章', '修改', '切换文章状态'],
        'posts.export' => ['文章', '导出', '导出文章数据'],
        // 个人资料
        'profile.update' => ['个人', '修改', '更新个人资料'],
        'profile.destroy' => ['个人', '删除', '删除个人账号'],
        'password.update' => ['个人', '修改', '修改个人密码'],
        // 系统设置 / 数据字典
        'settings.update' => ['设置', '修改', '更新系统设置'],
        'dict-types.store' => ['字典', '创建', '创建字典类型'],
        'dict-types.update' => ['字典', '修改', '更新字典类型'],
        'dict-types.destroy' => ['字典', '删除', '删除字典类型'],
        'dict-items.store' => ['字典', '创建', '创建字典项'],
        'dict-items.update' => ['字典', '修改', '更新字典项'],
        'dict-items.destroy' => ['字典', '删除', '删除字典项'],
    ];

    /** 写操作日志 */
    public static function log(
        ?int $userId,
        ?string $username,
        string $method,
        string $action,
        string $description,
        ?string $module = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): OperationLog {
        return OperationLog::query()->create([
            'user_id' => $userId,
            'username' => $username,
            'method' => $method,
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /** 根据路由名与请求解析 [module, action, description] */
    public static function resolve(Request $request): array
    {
        $routeName = $request->route()?->getName();

        if ($routeName && isset(self::ROUTE_MAP[$routeName])) {
            return self::ROUTE_MAP[$routeName];
        }

        // 兜底：按请求方法泛化
        $module = ucfirst((string) $request->segment(1));
        $action = match ($request->method()) {
            'POST' => '创建',
            'PUT', 'PATCH' => '修改',
            'DELETE' => '删除',
            default => strtoupper($request->method()),
        };
        $description = sprintf('%s%s（%s）', $module, $action, $routeName ?? $request->path());

        return [$module, $action, $description];
    }
}
