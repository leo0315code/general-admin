<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * 文章数据范围策略
 *
 * 规则：admin 可操作任意文章；其它用户只能操作自己的文章。
 * 由 Laravel 自动发现（App\Models\Post → App\Policies\PostPolicy），无需手动注册。
 */
class PostPolicy
{
    /** 列表/详情：有 post.manage 权限即可（路由层已拦） */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    /** 编辑/状态切换：admin 或作者本人 */
    public function update(User $user, Post $post): bool
    {
        return $user->isAdmin() || $post->user_id === $user->id;
    }

    /** 删除：admin 或作者本人 */
    public function delete(User $user, Post $post): bool
    {
        return $user->isAdmin() || $post->user_id === $user->id;
    }
}
