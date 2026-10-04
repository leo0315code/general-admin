<?php

namespace App\Http\Controllers;

use App\Exceptions\UploadException;
use App\Exports\PostsExport;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Attachment;
use App\Models\Post;
use App\Support\ListQuery;
use App\Support\Uploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            ->with('cover:id,path,disk,mime')
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

    /**
     * 创建文章表单
     *
     * 两层校验：按钮级权限 posts.create（能不能进这个页面）+ Policy（数据范围）。
     * 路由中间件只拦菜单级 post.manage，仅「看得到列表」不等于「能写」。
     */
    public function create(): View
    {
        Gate::authorize('posts.create');

        return view('posts.create');
    }

    /** 保存新文章 */
    public function store(StorePostRequest $request): RedirectResponse
    {
        Gate::authorize('posts.create');

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        // 发布时若未指定发布时间，则取当前时间
        $data['published_at'] = $request->input('published_at')
            ?? ($data['status'] === Post::STATUS_PUBLISHED ? now() : null);

        if (! $this->validateCoverOwnership($request, $data)) {
            return back()
                ->withErrors(['cover_attachment_id' => '封面附件不存在或无权使用。'])
                ->withInput();
        }

        $post = Post::query()->create($data);

        return redirect()
            ->route('posts.index')
            ->with('success', "文章「{$post->title}」创建成功。");
    }

    /** 编辑文章表单 */
    public function edit(Post $post): View
    {
        Gate::authorize('posts.update');
        $this->authorize('update', $post);

        return view('posts.edit', compact('post'));
    }

    /** 更新文章 */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        Gate::authorize('posts.update');
        $this->authorize('update', $post);

        $data = $request->validated();
        // 发布时若未指定发布时间，则取当前时间
        if ($data['status'] === Post::STATUS_PUBLISHED) {
            $data['published_at'] = $request->input('published_at') ?? $post->published_at ?? now();
        } else {
            $data['published_at'] = null;
        }

        if (! $this->validateCoverOwnership($request, $data)) {
            return back()
                ->withErrors(['cover_attachment_id' => '封面附件不存在或无权使用。'])
                ->withInput();
        }

        $post->update($data);

        return redirect()
            ->route('posts.edit', $post)
            ->with('success', "文章「{$post->title}」更新成功。");
    }

    /** 切换文章发布状态（draft <-> published） */
    public function toggleStatus(Post $post): RedirectResponse
    {
        Gate::authorize('posts.update');
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
        Gate::authorize('posts.destroy');
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
        // 路由已挂 permission:post.manage；此处再收紧到按钮级 posts.destroy，
        // 随后逐 id 复用 delete 策略（admin 或作者本人）
        Gate::authorize('posts.destroy');

        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, &$deleted, &$skipped) {
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
        });

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
        Gate::authorize('posts.update');

        $post = Post::query()->onlyTrashed()->findOrFail($id);
        $post->restore();

        return back()
            ->with('success', "文章「{$post->title}」已还原。");
    }

    /** 彻底删除文章（不可恢复） */
    public function forceDestroy(int $id): RedirectResponse
    {
        Gate::authorize('posts.destroy');

        $post = Post::query()->onlyTrashed()->findOrFail($id);
        $title = $post->title;
        $post->forceDelete();

        return back()
            ->with('success', "文章「{$title}」已彻底删除，无法恢复。");
    }

    /** 导出文章数据（Excel，跟随当前搜索/状态筛选） */
    public function export(Request $request)
    {
        // 导出是数据外泄路径：须有单独的 posts.export 按钮权限，而非仅「能看列表」
        Gate::authorize('posts.export');

        return Excel::download(
            new PostsExport($request->query('search'), $request->query('status')),
            '文章数据-'.date('YmdHis').'.xlsx'
        );
    }

    /** 文章编辑者是否有权操作封面（创建/更新时调用） */
    private function canManageCover(Request $request): bool
    {
        return $request->user()->can('posts.create') || $request->user()->can('posts.update');
    }

    /**
     * 封面归属校验：非 admin 只能使用自己的附件。
     * 返回 false 时由调用方回 422 错误。
     */
    private function validateCoverOwnership(Request $request, array $data): bool
    {
        $id = $data['cover_attachment_id'] ?? null;

        if (! $id) {
            return true;
        }

        $attachment = Attachment::query()->find($id);

        if (! $attachment || ! str_starts_with($attachment->mime, 'image/')) {
            return false;
        }

        return $request->user()->isAdmin() || $attachment->user_id === $request->user()->id;
    }

    /**
     * 文章封面内联上传（复用附件基座，落 attachments 表，仅放行图片）。
     *
     * 权限：posts.create 或 posts.update（能进文章表单的人即可传封面）。
     * 配额 / 类型白名单由 Uploader 统一把关。
     */
    public function coverUpload(Request $request): JsonResponse
    {
        abort_unless($this->canManageCover($request), 403);

        $request->validate([
            'file' => ['required', 'file', 'image', 'max:'.(int) config('uploads.max_size')],
        ], [
            'file.required' => '请选择封面图片。',
            'file.image' => '封面必须是图片文件。',
            'file.max' => '封面图片超过大小上限。',
        ]);

        try {
            $attachment = Uploader::store($request->file('file'), $request->user());
        } catch (UploadException $e) {
            return response()->json(['success' => false, 'message' => $e->userMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'id' => $attachment->id,
            'name' => $attachment->name,
            'preview_url' => route('posts.cover-preview', $attachment),
        ]);
    }

    /**
     * 文章封面预览（私有盘图片 inline 输出，供 <img> 显示）。
     *
     * 路由已挂 permission:post.manage（能看文章列表即可看封面）；
     * 此处只做数据范围：本人附件 / admin / 被任意文章引用为封面（列表页展示需要）。
     * 只允许 image/*，防止把任意附件当图输出。
     */
    public function coverPreview(Request $request, Attachment $attachment): StreamedResponse
    {
        if ($attachment->user_id !== $request->user()->id
            && ! $request->user()->isAdmin()
            && ! Post::query()->where('cover_attachment_id', $attachment->id)->exists()) {
            abort(403);
        }

        abort_if(! str_starts_with($attachment->mime, 'image/'), 404, '仅支持图片预览');

        $disk = Storage::disk($attachment->disk);

        abort_if(! $disk->exists($attachment->path), 404, '文件已不存在');

        return $disk->response($attachment->path);
    }
}
