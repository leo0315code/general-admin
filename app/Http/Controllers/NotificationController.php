<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 通知中心：站内消息列表 / 未读数 / 标记已读 / 删除
 *
 * 权限模型：通知属个人数据，**不挂菜单级 permission**——任何登录用户都能看自己的通知。
 *
 * 越权防护为什么不用 Policy：AuthServiceProvider 的 `Gate::before` 对 admin 无条件
 * 返回 true，Policy 对 admin 形同虚设（连他人的通知也会放行）。个人数据的归属校验
 * 必须绕过这层短路，因此这里在控制器内显式比对 user_id 并 abort(403)，
 * 且**故意不建 NotificationPolicy**，避免留下「看起来有保护」的假象。
 */
class NotificationController extends Controller
{
    /** 全部已读 / 单条已读的状态筛选键 */
    private const FILTER_ALL = 'all';

    private const FILTER_UNREAD = 'unread';

    /** 通知列表（分页 + 未读筛选） */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter') === self::FILTER_UNREAD ? self::FILTER_UNREAD : self::FILTER_ALL;

        $query = Notification::query()
            ->forUser($user)
            ->when($filter === self::FILTER_UNREAD, fn ($q) => $q->unread())
            ->latest();

        $notifications = $query->paginate(Notification::PER_PAGE)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => Notification::unreadCountFor($user->id),
        ]);
    }

    /** 顶部铃铛未读数（轻量 JSON，供前端轮询刷新未读徽章） */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['count' => Notification::unreadCountFor($request->user()->id)]);
    }

    /** 标记单条已读 */
    public function read(Request $request, Notification $notification): RedirectResponse
    {
        $this->assertOwner($request, $notification);

        $notification->markAsRead();
        Notification::forgetUnreadCount($request->user()->id);

        return back();
    }

    /** 全部标记已读 */
    public function readAll(Request $request): RedirectResponse
    {
        $user = $request->user();

        $updated = Notification::query()
            ->forUser($user)
            ->unread()
            ->update(['read_at' => now()]);

        Notification::forgetUnreadCount($user->id);

        return back()->with('success', $updated > 0 ? "已将 {$updated} 条通知标记为已读。" : '没有未读通知。');
    }

    /** 删除一条通知 */
    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        $this->assertOwner($request, $notification);

        $notification->delete();
        Notification::forgetUnreadCount($request->user()->id);

        return back()->with('success', '通知已删除。');
    }

    /** 通知归个人所有：非本人（含 admin）一律 403 */
    private function assertOwner(Request $request, Notification $notification): void
    {
        abort_if($notification->user_id !== $request->user()->id, 403);
    }
}
