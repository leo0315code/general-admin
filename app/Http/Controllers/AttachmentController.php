<?php

namespace App\Http\Controllers;

use App\Exceptions\UploadException;
use App\Models\Attachment;
use App\Support\Uploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 附件管理（上传基座）
 *
 * 三层权限：
 * - 菜单级 attachments.manage：能不能进附件页；
 * - 按钮级 attachments.upload / attachments.destroy：能不能传、能不能删；
 * - 数据范围：普通用户只能操作自己的附件，admin 可看全部（与通知同理，
 *   Gate::before 对 admin 短路，归属判断必须在这里显式做）。
 */
class AttachmentController extends Controller
{
    /** 每页显示数量 */
    protected const PER_PAGE = 15;

    /** 附件列表（分页 + 文件名搜索；非 admin 只看自己的） */
    public function index(Request $request): View
    {
        Gate::authorize('attachments.manage');

        $keyword = trim((string) $request->query('search'));

        $attachments = Attachment::query()
            ->with('user:id,name')
            ->search($keyword)
            ->unless($request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('attachments.index', [
            'attachments' => $attachments,
            'keyword' => $keyword,
            'maxSizeKb' => (int) config('uploads.max_size'),
            'can' => [
                'upload' => Gate::check('attachments.upload'),
                'destroy' => Gate::check('attachments.destroy'),
            ],
        ]);
    }

    /**
     * 上传附件
     *
     * 同步提交（原生表单）返回重定向；异步提交（前端 fetch，Accept: application/json）
     * 返回 JSON，让页面能直接提示结果，而不是整页刷新后看不出发生了什么。
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('attachments.upload');

        $request->validate([
            'file' => ['required', 'file', 'max:'.(int) config('uploads.max_size')],
        ], [
            'file.required' => '请选择要上传的文件。',
            'file.file' => '文件上传失败，请重试。',
            'file.max' => '文件超过大小上限。',
        ]);

        try {
            $attachment = Uploader::store($request->file('file'), $request->user());
        } catch (UploadException $e) {
            // 异步上传（前端 fetch）需要能拿到可读原因，前端直接提示
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->userMessage()], 422);
            }

            return back()->with('error', $e->userMessage());
        }

        $message = "附件「{$attachment->name}」上传成功。";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'id' => $attachment->id,
                'name' => $attachment->name,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * 下载附件（鉴权后由服务端读取输出，文件本身不在 public 目录）
     *
     * @return BinaryFileResponse|StreamedResponse
     */
    public function download(Request $request, Attachment $attachment)
    {
        Gate::authorize('attachments.manage');
        $this->assertAccessible($request, $attachment);

        $disk = Storage::disk($attachment->disk);

        abort_if(! $disk->exists($attachment->path), 404, '文件已不存在');

        return $disk->download($attachment->path, $attachment->name);
    }

    /** 删除附件（文件 + 记录） */
    public function destroy(Request $request, Attachment $attachment): RedirectResponse
    {
        Gate::authorize('attachments.destroy');
        $this->assertAccessible($request, $attachment);

        Uploader::delete($attachment);

        return back()->with('success', "附件「{$attachment->name}」已删除。");
    }

    /** 非 admin 只能操作自己的附件 */
    private function assertAccessible(Request $request, Attachment $attachment): void
    {
        $user = $request->user();

        abort_if($attachment->user_id !== $user->id && ! $user->isAdmin(), 403);
    }
}
