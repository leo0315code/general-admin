<?php

namespace App\Support;

use App\Models\Menu;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * 侧边栏导航构建器
 *
 * 数据来源为 menus 表（菜单即权限），按当前用户权限过滤后输出分组结构：
 * - dir 节点 → 分组标题，其下 menu 子节点为菜单项；分组内无可见菜单时整组隐藏
 * - menu 节点 → 无标题的独立菜单项
 * - button 节点 → 不进入导航
 *
 * 缓存策略（读多写少）：
 * - 只缓存「用户无关」的启用节点扁平数组（纯标量结构，serializable_classes=false 安全）
 *   到 menus.tree 键，永久有效直到显式失效；
 * - 权限过滤（$user->can，spatie 自带 24h 权限缓存）与激活态（request 相关）
 *   依赖当前请求，仍在每次请求内计算，不进缓存；
 * - Menu 模型 saved / deleted 事件自动失效（见 Menu::booted），覆盖控制器/脚本全部写路径。
 */
class Navigation
{
    /** 菜单树缓存键 */
    public const CACHE_KEY = 'menus.tree';

    /**
     * 生成当前用户可见的导航分组。
     *
     * @return list<array{title: string|null, items: list<array{title: string, url: string|null, icon: string|null, active: bool}>}>
     */
    public static function forUser(?Authenticatable $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [];
        foreach (self::tree(self::enabledNodes()) as $node) {
            // 目录：收集其下可见菜单，空组直接不渲染
            if ($node['type'] === Menu::TYPE_DIR) {
                $items = [];

                foreach ($node['children'] as $child) {
                    if ($child['type'] === Menu::TYPE_MENU && self::allows($user, $child['permission_name'])) {
                        $items[] = self::item($child);
                    }
                }

                if ($items !== []) {
                    $groups[] = ['title' => $node['title'], 'items' => $items];
                }

                continue;
            }

            // 顶级菜单：独立展示（无分组标题）
            if ($node['type'] === Menu::TYPE_MENU && self::allows($user, $node['permission_name'])) {
                $groups[] = ['title' => null, 'items' => [self::item($node)]];
            }
        }

        return $groups;
    }

    /**
     * 启用的菜单节点扁平数组（已按 sort/id 排序），缓存读取。
     *
     * @return list<array{id: int, pid: int, type: string, title: string, permission_name: string|null, icon: string|null, route: string|null}>
     */
    protected static function enabledNodes(): array
    {
        // 缓存只存标量数组（与 Dict 同理：serializable_classes=false，禁止对象反序列化）
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return Menu::query()->enabled()->ordered()->get()
                ->map(fn (Menu $menu): array => [
                    'id' => (int) $menu->id,
                    'pid' => (int) $menu->pid,
                    'type' => (string) $menu->type,
                    'title' => (string) $menu->title,
                    'permission_name' => $menu->permission_name,
                    'icon' => $menu->icon,
                    'route' => $menu->route,
                ])
                ->all();
        });
    }

    /**
     * 内存构树（同 Menu::nest 逻辑，作用于缓存数组）。
     *
     * @param  list<array{id: int, pid: int, type: string, title: string, permission_name: string|null, icon: string|null, route: string|null}>  $nodes
     * @return list<array{id: int, pid: int, type: string, title: string, permission_name: string|null, icon: string|null, route: string|null, children: list<array{...}>}>
     */
    protected static function tree(array $nodes, int $pid = 0): array
    {
        $branches = [];

        foreach ($nodes as $node) {
            if ($node['pid'] !== $pid) {
                continue;
            }

            $node['children'] = self::tree($nodes, $node['id']);
            $branches[] = $node;
        }

        return $branches;
    }

    /** 权限判定：未配置权限标识的菜单登录后即可见 */
    protected static function allows(Authenticatable $user, ?string $permissionName): bool
    {
        if (blank($permissionName)) {
            return true;
        }

        return method_exists($user, 'can') && $user->can($permissionName);
    }

    /**
     * 菜单项视图数据。
     *
     * @param  array{title: string, route: string|null, icon: string|null}  $node
     * @return array{title: string, url: string|null, icon: string|null, active: bool}
     */
    protected static function item(array $node): array
    {
        return [
            'title' => $node['title'],
            'url' => $node['route'] && Route::has($node['route']) ? route($node['route']) : null,
            'icon' => $node['icon'],
            'active' => self::isActive($node['route']),
        ];
    }

    /**
     * 菜单激活判定（同 Menu::activePattern + isActive 逻辑，作用于缓存的路由名）。
     * users.index → users.*、dashboard → dashboard：列表页与详情页都保持高亮。
     */
    protected static function isActive(?string $route): bool
    {
        if (! $route) {
            return false;
        }

        $pattern = Str::contains($route, '.')
            ? Str::before($route, '.').'.*'
            : $route;

        return request()->routeIs($pattern);
    }

    /** 失效菜单树缓存（Menu 模型 saved / deleted 事件自动调用） */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
