<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * 菜单 / 权限节点种子（参考 BuildAdmin 的权限节点模型）
 *
 * 本项目「菜单即权限」：菜单树是权限的唯一来源，
 * 本 Seeder 写入菜单节点的同时通过 Menu::syncPermission() 同步 spatie 权限记录，
 * 因此权限清单只需在此维护一份。
 *
 * 节点类型：dir 目录 / menu 菜单 / button 按钮权限。
 */
class MenuPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedNodes($this->nodes(), 0);
    }

    /** 逐层写入节点并同步权限（按「父级 + 名称」幂等，可重复执行） */
    protected function seedNodes(array $nodes, int $pid): void
    {
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            unset($node['children']);

            $menu = Menu::query()->updateOrCreate(
                ['pid' => $pid, 'title' => $node['title']],
                array_merge($node, ['pid' => $pid])
            );

            $menu->syncPermission();

            if ($children !== []) {
                $this->seedNodes($children, $menu->id);
            }
        }
    }

    /**
     * 后台菜单 / 权限节点清单
     *
     * @return list<array<string, mixed>>
     */
    protected function nodes(): array
    {
        return [
            [
                'type' => Menu::TYPE_DIR,
                'title' => '概览',
                'sort' => 10,
                'children' => [
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '仪表盘',
                        'permission_name' => 'dashboard.view',
                        'icon' => 'heroicon-o-squares-2x2',
                        'route' => 'dashboard',
                        'sort' => 10,
                        'remark' => '访问后台仪表盘与统计数据',
                    ],
                ],
            ],
            [
                'type' => Menu::TYPE_DIR,
                'title' => '内容管理',
                'sort' => 20,
                'children' => [
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '文章管理',
                        'permission_name' => 'post.manage',
                        'icon' => 'heroicon-o-document-text',
                        'route' => 'posts.index',
                        'sort' => 10,
                        'remark' => '管理文章：增删改、发布/下线',
                        'children' => [
                            ['type' => Menu::TYPE_BUTTON, 'title' => '新建文章', 'permission_name' => 'posts.create', 'sort' => 10],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '编辑文章', 'permission_name' => 'posts.update', 'sort' => 20],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '删除文章', 'permission_name' => 'posts.destroy', 'sort' => 30],
                        ],
                    ],
                ],
            ],
            [
                'type' => Menu::TYPE_DIR,
                'title' => '系统管理',
                'sort' => 30,
                'children' => [
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '用户管理',
                        'permission_name' => 'user.manage',
                        'icon' => 'heroicon-o-users',
                        'route' => 'users.index',
                        'sort' => 10,
                        'remark' => '管理用户：增删改、分配角色、重置密码',
                        'children' => [
                            ['type' => Menu::TYPE_BUTTON, 'title' => '新增用户', 'permission_name' => 'users.create', 'sort' => 10],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '编辑用户', 'permission_name' => 'users.update', 'sort' => 20],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '删除用户', 'permission_name' => 'users.destroy', 'sort' => 30],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '重置密码', 'permission_name' => 'users.reset-password', 'sort' => 40],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '导入用户', 'permission_name' => 'users.import', 'sort' => 50],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '导出用户', 'permission_name' => 'users.export', 'sort' => 60],
                        ],
                    ],
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '角色管理',
                        'permission_name' => 'role.manage',
                        'icon' => 'heroicon-o-shield-check',
                        'route' => 'roles.index',
                        'sort' => 20,
                        'remark' => '管理角色与权限分配',
                        'children' => [
                            ['type' => Menu::TYPE_BUTTON, 'title' => '新增角色', 'permission_name' => 'roles.create', 'sort' => 10],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '编辑角色', 'permission_name' => 'roles.update', 'sort' => 20],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '删除角色', 'permission_name' => 'roles.destroy', 'sort' => 30],
                        ],
                    ],
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '菜单管理',
                        'permission_name' => 'menu.manage',
                        'icon' => 'heroicon-o-rectangle-stack',
                        'route' => 'menus.index',
                        'sort' => 30,
                        'remark' => '维护菜单与权限节点（菜单即权限）',
                        'children' => [
                            ['type' => Menu::TYPE_BUTTON, 'title' => '新增菜单', 'permission_name' => 'menus.create', 'sort' => 10],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '编辑菜单', 'permission_name' => 'menus.update', 'sort' => 20],
                            ['type' => Menu::TYPE_BUTTON, 'title' => '删除菜单', 'permission_name' => 'menus.destroy', 'sort' => 30],
                        ],
                    ],
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '数据字典',
                        'permission_name' => 'dict.manage',
                        'icon' => 'heroicon-o-bookmark-square',
                        'route' => 'dict-types.index',
                        'sort' => 40,
                        'remark' => '管理数据字典类型与字典项',
                    ],
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '操作日志',
                        'permission_name' => 'log.manage',
                        'icon' => 'heroicon-o-clipboard-document-list',
                        'route' => 'logs.index',
                        'sort' => 50,
                        'remark' => '查看登录审计与操作日志',
                    ],
                    [
                        'type' => Menu::TYPE_MENU,
                        'title' => '系统设置',
                        'permission_name' => 'settings.manage',
                        'icon' => 'heroicon-o-cog-6-tooth',
                        'route' => 'settings.index',
                        'sort' => 60,
                        'remark' => '站点名称、列表分页、版权信息等系统配置',
                    ],
                ],
            ],
        ];
    }
}
