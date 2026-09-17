<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 文章管理控制器（示例 CRUD 模板）
 *
 * 列表（分页+搜索+状态筛选）、创建、编辑、删除（软删除）、状态切换。
 * 后续业务模块可复制本控制器 + 视图 + 迁移作为模板。
 */
class PostController extends Controller
{
    /** 每页显示数量 */
    protected const PER_PAGE = 15;

    /** 文章列表：分页 + 标题搜索 + 状态筛选 */
    public function index(Request $request): View
    {
        $keyword = $request->query('search');
        $status = $request->query('status');

        $posts = Post::query()
            ->with('user:id,name')
            ->search($keyword)
            ->ofStatus($status)
            ->latest()
            ->paginate(config('app.pagination', self::PER_PAGE))
            ->withQueryString();

        return view('posts.index', compact('posts', 'keyword', 'status'));
    }

    /** 创建文章表单 */
    public function create(): View
    {
        return view('posts.create');
    }

    /** 保存新文章 */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        // 发布时若未指定发布时间，则取当前时间
        $data['published_at'] = $request->input('published_at')
            ?? ($data['status'] === Post::STATUS_PUBLISHED ? now() : null);

        Post::query()->create($data);

        return redirect()
            ->route('posts.index')
            ->with('success', '文章创建成功。');
    }

    /** 编辑文章表单 */
    public function edit(Post $post): View
    {
        return view('posts.edit', compact('post'));
    }

    /** 更新文章 */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $data = $request->validated();
        // 发布时若未指定发布时间，则取当前时间
        if ($data['status'] === Post::STATUS_PUBLISHED) {
            $data['published_at'] = $request->input('published_at') ?? $post->published_at ?? now();
        } else {
            $data['published_at'] = null;
        }

        $post->update($data);

        return redirect()
            ->route('posts.edit', $post)
            ->with('success', "文章「{$post->title}」更新成功。");
    }

    /** 切换文章发布状态（draft <-> published） */
    public function toggleStatus(Post $post): RedirectResponse
    {
        if ($post->isPublished()) {
            $post->update(['status' => Post::STATUS_DRAFT, 'published_at' => null]);
            $message = "文章「{$post->title}」已转为草稿。";
        } else {
            $post->update([
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => $post->published_at ?? now(),
            ]);
            $message = "文章「{$post->title}」已发布。";
        }

        return redirect()
            ->route('posts.index')
            ->with('success', $message);
    }

    /** 删除文章（软删除） */
    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()
            ->route('posts.index')
            ->with('success', "文章「{$post->title}」已删除（软删除）。");
    }

    /** 导出文章数据（Excel） */
    public function export()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PostsExport,
            '文章数据-'.date('YmdHis').'.xlsx'
        );
    }
}
