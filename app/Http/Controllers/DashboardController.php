<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * 仪表盘控制器：统计卡片 + 最近文章
 */
class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::query()->count(),
            'roles' => Role::query()->count(),
            'posts' => Post::query()->count(),
            'published_posts' => Post::query()->published()->count(),
        ];

        $recentPosts = Post::query()
            ->with('user:id,name')
            ->latest()
            ->take(5)
            ->get();

        return view('dashboard', compact('stats', 'recentPosts'));
    }
}
