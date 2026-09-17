<?php

namespace App\Support;

use App\Models\Menu;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * 侧边栏导航构建器
 *
 * 数据来源为 menus 表（菜单即权限），按当前用户权限过滤后输出分组结构：
 * - dir 节点 → 分组标题，其下 menu 子节点为菜单项；分组内无可见菜单时整组隐藏
 * - 顶级 menu 节点 → 无标题的独立菜单项
 * - button 节点 → 不进入导航
 */
class Navigation
{
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

        $nodes = Menu::query()->enabled()->ordered()->get();
        $groups = [];

        foreach (Menu::tree($nodes) as $node) {
            // 目录：收集其下可见菜单，空组直接不渲染
            if ($node->isDir()) {
                $items = [];

                foreach ($node->children as $child) {
                    if ($child->isMenu() && self::allows($user, $child)) {
                        $items[] = self::item($child);
                    }
                }

                if ($items !== []) {
                    $groups[] = ['title' => $node->title, 'items' => $items];
                }

                continue;
            }

            // 顶级菜单：独立展示（无分组标题）
            if ($node->isMenu() && self::allows($user, $node)) {
                $groups[] = ['title' => null, 'items' => [self::item($node)]];
            }
        }

        return $groups;
    }

    /** 权限判定：未配置权限标识的菜单登录后即可见 */
    protected static function allows(Authenticatable $user, Menu $menu): bool
    {
        if (blank($menu->permission_name)) {
            return true;
        }

        return method_exists($user, 'can') && $user->can($menu->permission_name);
    }

    /**
     * 菜单项视图数据。
     *
     * @return array{title: string, url: string|null, icon: string|null, active: bool}
     */
    protected static function item(Menu $menu): array
    {
        return [
            'title' => $menu->title,
            'url' => $menu->route && Route::has($menu->route) ? route($menu->route) : null,
            'icon' => $menu->icon,
            'active' => $menu->isActive(),
        ];
    }
}
