<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 文章种子数据：生成若干测试文章，混搭草稿/已发布状态
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        // 固定取最早注册的 5 位用户作为作者，避免隐式排序导致每次重建结果不一致
        $authors = User::query()->orderBy('id')->limit(5)->pluck('id')->all();

        if (empty($authors)) {
            return;
        }

        $titles = [
            '通用管理后台使用说明',
            '如何在后台创建新用户',
            '角色与权限配置指南',
            '文章发布流程介绍',
            '软删除机制说明',
            '暗色模式切换技巧',
            'Tailwind v4 暗色模式实现',
            'MySQL 生产环境配置建议',
            'Laravel 测试最佳实践',
            '后台界面定制方法',
            '数据备份与恢复',
            '常见问题解答',
            '系统更新日志',
            '权限中间件使用示例',
            '示例 CRUD 模板说明',
            '用户搜索与分页',
            '角色分配注意事项',
            '状态切换功能演示',
            '快速上手：三分钟搭建业务模块',
            '后台安全建议',
        ];

        foreach ($titles as $i => $title) {
            $published = $i % 3 !== 0; // 约 2/3 已发布

            Post::query()->updateOrCreate(
                ['title' => $title],
                [
                    'user_id' => $authors[$i % count($authors)],
                    'content' => "这是文章《{$title}》的示例内容。\n\n通用管理后台提供完整的 CRUD、RBAC 权限控制与现代化界面，可直接复制文章模块作为后续业务模块的模板。",
                    'status' => $published ? Post::STATUS_PUBLISHED : Post::STATUS_DRAFT,
                    'published_at' => $published ? now()->subDays($i)->setTime(9, 30) : null,
                ]
            );
        }
    }
}
