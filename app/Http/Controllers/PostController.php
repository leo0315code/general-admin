<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    /** 文章列表：分页 + 标题搜索 + 状态筛选 + 每页条数/排序（ListQuery 白名单） */
    public function index(Request $request): View
    {
        $keyword = $request->query('search');
        $status = $request->query('status');

        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'title', 'status', 'published_at', 'created_at']
        );

        $posts = Post::query()
            ->with('user:id,name')
            ->search($keyword)
            ->ofStatus($status)
            ->when(
                $sort,
                fn ($query) => $query->orderBy($sort, $dir),
                fn ($query) => $query->latest()
            )
            ->paginate($perPage)
            ->withQueryString();

        $trashedCount = Post::query()->onlyTrashed()->count();

        return view('posts.index', compact('posts', 'keyword', 'status', 'trashedCount', 'sort', 'dir'));
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
        $this->authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    /** 更新文章 */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

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
        $this->authorize('update', $post);

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
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()
            ->route('posts.index')
            ->with('success', "文章「{$post->title}」已删除（软删除）。");
    }

    /** 回收站：已删除文章列表（分页 + 搜索 + 状态筛选 + 每页条数/排序） */
    public function trash(Request $request): View
    {
        $keyword = $request->query('search');
        $status = $request->query('status');

        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'title', 'status', 'published_at', 'created_at']
        );

        $posts = Post::query()
            ->onlyTrashed()
            ->with('user:id,name')
            ->search($keyword)
            ->ofStatus($status)
            ->when(
                $sort,
                fn ($query) => $query->orderBy($sort, $dir),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        return view('posts.trash', compact('posts', 'keyword', 'status', 'sort', 'dir'));
    }

    /** 批量删除文章（软删除；逐条复用 delete 策略：admin 或作者本人） */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        // 路由已挂 permission:post.manage；此处再显式确认，随后逐 id 复用 delete 策略（destroy 语义）
        Gate::authorize('post.manage');

        $deleted = 0;
        $skipped = 0;

        foreach ($this->validatedIds($request) as $id) {
            $post = Post::query()->find($id);

            if (! $post) {
                continue;
            }

            if (! $request->user()->can('delete', $post)) {
                $skipped++;

                continue;
            }

            $post->delete();
            $deleted++;
        }

        $message = "已删除 {$deleted} 篇文章。";

        if ($skipped > 0) {
            $message .= " 跳过 {$skipped} 篇无权操作的文章。";
        }

        return back()->with('success', $message);
    }

    /**
     * 批量操作 ID 白名单清洗：仅保留正整数，去重。
     *
     * @return list<int>
     */
    protected function validatedIds(Request $request): array
    {
        $ids = $request->input('ids', []);

        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** 还原软删除文章 */
    public function restore(int $id): RedirectResponse
    {
        $post = Post::query()->onlyTrashed()->findOrFail($id);
        $post->restore();

        return back()
            ->with('success', "文章「{$post->title}」已还原。");
    }

    /** 彻底删除文章（不可恢复） */
    public function forceDestroy(int $id): RedirectResponse
    {
        $post = Post::query()->onlyTrashed()->findOrFail($id);
        $title = $post->title;
        $post->forceDelete();

        return back()
            ->with('success', "文章「{$title}」已彻底删除，无法恢复。");
    }

    /** 导出文章数据（Excel，跟随当前搜索/状态筛选） */
    public function export(Request $request)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PostsExport($request->query('search'), $request->query('status')),
            '文章数据-'.date('YmdHis').'.xlsx'
        );
    }
}
