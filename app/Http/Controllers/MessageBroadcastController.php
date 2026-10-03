<?php

namespace App\Http\Controllers;

use App\Models\NotificationBroadcast;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * 主动发送消息（群发站内通知）
 *
 * 三种范围：指定用户（多选）/ 按角色 / 全员（仅启用状态）。
 * 发送后生成一条广播记录 + N 条通知；撤回 = 删掉这批通知并打 revoked_at。
 *
 * 权限分两级：messages.manage 看历史、messages.create 发送、messages.revoke 撤回。
 */
class MessageBroadcastController extends Controller
{
    /** 历史列表每页条数 */
    protected const PER_PAGE = 15;

    /** 单次最多勾选多少用户（防误操作把全库刷一遍） */
    protected const MAX_USER_IDS = 500;

    /** 发送历史 */
    public function index(): View
    {
        Gate::authorize('messages.manage');

        $broadcasts = NotificationBroadcast::query()
            ->with('sender:id,name')
            ->withCount('notifications')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('messages.index', [
            'broadcasts' => $broadcasts,
            'can' => [
                'create' => Gate::check('messages.create'),
                'revoke' => Gate::check('messages.revoke'),
            ],
        ]);
    }

    /** 发送表单（Vue 组件承载范围选择 + 用户搜索） */
    public function create(): View
    {
        Gate::authorize('messages.create');

        return view('messages.create', [
            'roles' => Role::query()->orderBy('name')->pluck('name')->all(),
        ]);
    }

    /** 发送消息 */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('messages.create');

        $data = $request->validate([
            'scope' => ['required', Rule::in([
                NotificationBroadcast::SCOPE_USERS,
                NotificationBroadcast::SCOPE_ROLE,
                NotificationBroadcast::SCOPE_ALL,
            ])],
            // required 不能省：字段缺失时 array/min 这类规则不会执行（只有 implicit 规则才会）
            'user_ids' => ['exclude_unless:scope,'.NotificationBroadcast::SCOPE_USERS, 'required', 'array', 'min:1', 'max:'.self::MAX_USER_IDS],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'role' => ['exclude_unless:scope,'.NotificationBroadcast::SCOPE_ROLE, 'required', 'string', 'max:50', $this->roleExists()],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:2000'],
            'link' => ['nullable', 'string', 'max:500'],
        ], [
            'scope.required' => '请选择发送范围。',
            'user_ids.min' => '请至少选择一位接收用户。',
            'user_ids.max' => '单次最多选择 '.self::MAX_USER_IDS.' 位用户，如需全员请改用「全员」范围。',
            'user_ids.*.exists' => '所选用户不存在。',
            'role.exists' => '所选角色不存在。',
            'title.required' => '请填写消息标题。',
        ]);

        $broadcast = NotificationBroadcast::query()->create([
            'user_id' => $request->user()->getKey(),
            'type' => NotificationBroadcast::TYPE_CUSTOM,
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            // 站外地址一律丢弃，避免消息里夹带钓鱼链接
            'link' => Notifier::safeLink($data['link'] ?? null),
            'scope' => $data['scope'],
            'role' => $data['role'] ?? null,
        ]);

        $recipients = $broadcast->resolveRecipients($data['user_ids'] ?? []);
        $sent = Notifier::dispatch($broadcast, $recipients);

        $broadcast->update(['recipients_count' => $sent]);

        return redirect()
            ->route('messages.index')
            ->with('success', $sent > 0
                ? "消息已发送给 {$sent} 位用户。"
                : '没有符合条件的接收用户，消息未发出。');
    }

    /** 撤回：删掉这批通知并标记撤回时间 */
    public function revoke(Request $request, NotificationBroadcast $broadcast): RedirectResponse|JsonResponse
    {
        Gate::authorize('messages.revoke');

        abort_if($broadcast->isRevoked(), 422, '该消息已撤回。');

        $deleted = Notifier::revoke($broadcast);

        $message = "已撤回，删除 {$deleted} 条通知。";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'deleted' => $deleted]);
        }

        return back()->with('success', $message);
    }

    /** 角色名必须存在于 spatie 的 roles 表（表名走配置，避免硬编码） */
    private function roleExists(): Exists
    {
        return Rule::exists(config('permission.table_names.roles', 'roles'), 'name');
    }
}
