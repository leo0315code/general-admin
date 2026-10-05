<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Exports\UsersImportErrorsExport;
use App\Exports\UsersImportTemplate;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Imports\UsersImport;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\AccountCredentials;
use App\Support\BulkAction;
use App\Support\ListQuery;
use App\Support\Notifier;
use App\Support\Sessions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * 用户管理控制器（基于 spatie/laravel-permission）
 *
 * 列表（分页+搜索）、创建、编辑、删除（软删除）、分配角色、重置密码。
 */
class UserController extends Controller
{
    /** 每页显示数量 */
    protected const PER_PAGE = 15;

    /** 导入失败明细缓存键前缀（+ token） */
    protected const IMPORT_ERRORS_PREFIX = 'users.import-errors.';

    /** 用户列表：分页 + 关键字搜索（姓名/邮箱）+ 每页条数/排序（ListQuery 白名单） */
    public function index(Request $request): View
    {
        $keyword = $request->query('search');

        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'name', 'email', 'status', 'created_at', 'last_login_at']
        );

        $users = User::query()
            ->with('roles:id,name')
            ->when($keyword, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%'])
                        ->orWhereRaw("email LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%']);
                });
            })
            ->when(
                $sort,
                fn ($query) => $query->orderBy($sort, $dir),
                fn ($query) => $query->latest()
            )
            ->paginate($perPage)
            ->withQueryString();

        $trashedCount = User::query()->onlyTrashed()->count();

        return view('users.index', compact('users', 'keyword', 'trashedCount', 'sort', 'dir'));
    }

    /** 创建用户表单 */
    public function create(): View
    {
        Gate::authorize('users.create');

        $roles = Role::query()->orderBy('id')->get();

        return view('users.create', compact('roles'));
    }

    /** 保存新用户并分配角色 */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('users.create');

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($request->validated('roles', []));

        // 新建账号：邮件告知初始密码 + 站内通知（失败均不影响创建结果）
        $this->notifyCredentials($user, (string) $request->validated('password'), 'created');
        $this->notifyInApp($user, 'created');

        return redirect()
            ->route('users.index')
            ->with('success', "用户「{$user->name}」创建成功。");
    }

    /** 编辑用户表单 */
    public function edit(User $user): View
    {
        Gate::authorize('users.update');

        $user->load('roles:id,name');
        $roles = Role::query()->orderBy('id')->get();
        $userRoleIds = $user->roles->pluck('id')->all();

        return view('users.edit', compact('user', 'roles', 'userRoleIds'));
    }

    /** 更新用户资料与角色 */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('users.update');

        // 最后 admin 保护：不得通过编辑移除其 admin 角色
        $roleNames = Role::query()
            ->whereIn('id', $request->validated('roles', []))
            ->pluck('name');

        if ($this->isLastActiveAdmin($user) && ! $roleNames->contains(User::ROLE_ADMIN)) {
            return back()
                ->with('error', "「{$user->name}」是最后一个启用的管理员，不能移除其 admin 角色。");
        }

        $data = $request->safe()->only(['name', 'email']);

        // 仅在填写新密码时更新密码
        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);
        $user->syncRoles($request->validated('roles', []));

        return redirect()
            ->route('users.edit', $user)
            ->with('success', "用户「{$user->name}」更新成功。");
    }

    /** 重置用户密码（不修改其它资料） */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('users.reset-password');

        $request->validate([
            'new_password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'new_password.required' => '请输入新密码。',
            'new_password.min' => '新密码至少需要 10 个字符，且需同时包含字母与数字。',
            'new_password.letters' => '新密码需包含字母。',
            'new_password.numbers' => '新密码需包含数字。',
            'new_password.confirmed' => '两次输入的新密码不一致。',
        ]);

        $user->update([
            'password' => Hash::make($request->input('new_password')),
            // 管理员重置密码后，用户下次登录需先改密
            'must_change_password' => true,
        ]);

        // 管理员重置密码后，被重置者的全部旧会话一并踢掉（含管理员的 recaller 无关，
        // 这里踢的是 $user 的会话，不是操作者自己的）
        Sessions::invalidateAll($user->id);

        // 重置密码：告知新密码（失败不影响重置结果）
        $this->notifyCredentials($user, (string) $request->input('new_password'), 'reset');
        $this->notifyInApp($user, 'reset');

        return redirect()
            ->route('users.edit', $user)
            ->with('success', "用户「{$user->name}」的密码已重置，下次登录需修改密码。");
    }

    /**
     * 发送账号凭据邮件（新建 / 重置密码）。
     *
     * 两处兜底：
     * - 用户未填邮箱（email 可为空）→ 直接跳过，不尝试发送；
     * - 邮件通道异常（SMTP 未配置/不可达）→ 记日志后继续，
     *   绝不让后台操作因发信失败而中断（密码已改，通知失败不能回滚业务）。
     */
    protected function notifyCredentials(User $user, string $plainPassword, string $scene): void
    {
        if (blank($user->email)) {
            return;
        }

        try {
            $user->notify(new AccountCredentials($plainPassword, $scene));
        } catch (Throwable $e) {
            Log::warning('账号凭据邮件发送失败', [
                'user_id' => $user->id,
                'scene' => $scene,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 站内通知（新建账号 / 重置密码）。
     *
     * 与邮件不同：站内通知**不依赖邮箱**，邮箱为空的用户同样能收到；
     * 写库异常记日志后继续，不阻断后台操作。
     */
    protected function notifyInApp(User $user, string $scene): void
    {
        $isCreated = $scene === 'created';

        try {
            Notifier::send(
                $user,
                $isCreated ? Notification::TYPE_ACCOUNT_CREATED : Notification::TYPE_PASSWORD_RESET,
                $isCreated ? '账号已创建' : '登录密码已被重置',
                $isCreated
                    ? "管理员已为你创建账号「{$user->name}」。初始密码已通过邮件发送（若未设置邮箱请联系管理员），登录后请立即修改。"
                    : '管理员已重置你的登录密码，下次登录需先修改密码。若非本人操作，请联系管理员。',
                route('profile.edit'),
            );
        } catch (Throwable $e) {
            Log::warning('站内通知写入失败', [
                'user_id' => $user->id,
                'scene' => $scene,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** 切换账号启停状态（禁止停用自己；最后一个启用 admin 不可停用） */
    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('users.update');

        if ($user->is(auth()->user())) {
            return back()->with('error', '不能停用当前登录的账号。');
        }

        if ($user->isActive() && $this->isLastActiveAdmin($user)) {
            return back()
                ->with('error', "「{$user->name}」是最后一个启用的管理员，不能停用。");
        }

        $user->update(['status' => ! $user->isActive()]);

        return back()
            ->with('success', $user->isActive()
                ? "用户「{$user->name}」已启用。"
                : "用户「{$user->name}」已停用，将无法登录。");
    }

    /** 删除用户（软删除；禁止删除自己；最后一个启用 admin 不可删除） */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('users.destroy');

        if ($user->is(auth()->user())) {
            return redirect()
                ->route('users.index')
                ->with('error', '不能删除当前登录的账号。');
        }

        if ($this->isLastActiveAdmin($user)) {
            return redirect()
                ->route('users.index')
                ->with('error', "「{$user->name}」是最后一个启用的管理员，不能删除。");
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "用户「{$user->name}」已删除（软删除）。");
    }

    /** 批量删除用户（软删除；跳过自己与最后一个启用的 admin；整体事务） */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        Gate::authorize('users.destroy');

        ['done' => $deleted, 'skipped' => $skipped] = (new BulkAction($request))->run(
            fn (int $id) => User::query()->find($id),
            fn (User $user) => $user->is(auth()->user()) || $this->isLastActiveAdmin($user),
            fn (User $user) => $user->delete(),
        );

        $message = "已删除 {$deleted} 个用户。";

        if ($skipped > 0) {
            $message .= " 跳过 {$skipped} 个受限项（自己或最后一个启用的管理员）。";
        }

        return back()->with('success', $message);
    }

    /** 批量切换账号启停状态（跳过自己与最后一个启用的 admin；整体事务） */
    public function bulkToggleStatus(Request $request): RedirectResponse
    {
        Gate::authorize('users.update');

        ['done' => $changed, 'skipped' => $skipped] = (new BulkAction($request))->run(
            fn (int $id) => User::query()->find($id),
            fn (User $user) => $user->is(auth()->user())
                || ($user->isActive() && $this->isLastActiveAdmin($user)),
            fn (User $user) => $user->update(['status' => ! $user->isActive()]),
        );

        $message = "已更新 {$changed} 个用户的状态。";

        if ($skipped > 0) {
            $message .= " 跳过 {$skipped} 个受限项（自己或最后一个启用的管理员）。";
        }

        return back()->with('success', $message);
    }

    /** 回收站：已删除用户列表（分页 + 搜索 + 每页条数/排序） */
    public function trash(Request $request): View
    {
        $keyword = $request->query('search');

        [$perPage, $sort, $dir] = ListQuery::resolve(
            $request,
            ['id', 'name', 'email', 'status', 'created_at', 'last_login_at']
        );

        $users = User::query()
            ->onlyTrashed()
            ->with('roles:id,name')
            ->when($keyword, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->whereRaw("name LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%'])
                        ->orWhereRaw("email LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%']);
                });
            })
            ->when(
                $sort,
                fn ($query) => $query->orderBy($sort, $dir),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        return view('users.trash', compact('users', 'keyword', 'sort', 'dir'));
    }

    /** 还原软删除用户 */
    public function restore(int $id): RedirectResponse
    {
        Gate::authorize('users.update');

        $user = User::query()->onlyTrashed()->findOrFail($id);
        $user->restore();

        return back()
            ->with('success', "用户「{$user->name}」已还原，可正常登录。");
    }

    /** 彻底删除用户（不可恢复） */
    public function forceDestroy(int $id): RedirectResponse
    {
        Gate::authorize('users.destroy');

        $user = User::query()->onlyTrashed()->findOrFail($id);
        $name = $user->name;
        $user->forceDelete();

        return back()
            ->with('success', "用户「{$name}」已彻底删除，无法恢复。");
    }

    /**
     * 用户搜索（轻量 JSON，供「发送消息」选人）
     *
     * 只返回 id/name/email，限量 20 条；不发密码、角色等敏感字段。
     * 权限：有用户管理权限或能发消息的人都可用。
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless(Gate::check('user.manage') || Gate::check('messages.create'), 403);

        $keyword = trim((string) $request->query('q'));

        $users = User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->when($keyword !== '', fn ($query) => $query->where(
                fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%'])
                    ->orWhereRaw("email LIKE ? ESCAPE '!'", ['%'.escape_like($keyword).'%'])
            ))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    /** 导出用户数据（Excel，跟随当前搜索条件） */
    public function export(Request $request)
    {
        Gate::authorize('users.export');

        $keyword = $request->query('search');

        return Excel::download(new UsersExport($keyword), '用户数据-'.date('YmdHis').'.xlsx');
    }

    /** 下载用户导入模板 */
    public function importTemplate()
    {
        Gate::authorize('users.import');

        return Excel::download(new UsersImportTemplate, '用户导入模板.xlsx');
    }

    /** 批量导入用户（Excel） */
    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('users.import');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ], [
            'file.required' => '请选择要导入的文件。',
            'file.mimes' => '仅支持 xlsx / xls 格式。',
            'file.max' => '导入文件不能超过 5MB。',
        ]);

        UsersImport::reset();
        Excel::import(new UsersImport, $request->file('file'));

        $success = UsersImport::$created;
        $errors = UsersImport::$errors;

        if ($success > 0) {
            $message = "导入完成：成功 {$success} 条。";
        } else {
            $message = '导入完成：没有新增用户。';
        }
        if ($errors) {
            $message .= ' 失败 '.count($errors).' 条。';
        }

        $redirect = back()->with('success', $message);

        if ($errors !== []) {
            $redirect->with('import_errors', $errors)
                ->with('import_errors_token', $this->cacheImportErrors(UsersImport::$failedRows));
        }

        // 导入结果留痕：flash 关页即失，通知中心可回看
        $this->notifyImportResult($request->user(), $success, count($errors));

        return $redirect;
    }

    /**
     * 下载上次导入的失败明细（xlsx）。
     *
     * 明细暂存缓存 10 分钟并用一次性 token 换取：既不把若干行数据塞进 session
     * （flash 体积有限），也不在 URL 里暴露用户信息。
     */
    public function downloadImportErrors(string $token)
    {
        Gate::authorize('users.import');

        $rows = Cache::pull(self::IMPORT_ERRORS_PREFIX.$token);

        if (! $rows) {
            return redirect()
                ->route('users.index')
                ->with('error', '失败明细已过期，请重新导入后下载。');
        }

        return Excel::download(
            new UsersImportErrorsExport($rows),
            '用户导入失败明细_'.now()->format('Ymd_His').'.xlsx'
        );
    }

    /** 导入结果写进通知中心（成功/失败条数），便于事后回看 */
    protected function notifyImportResult(User $operator, int $success, int $failed): void
    {
        try {
            Notifier::send(
                $operator,
                Notification::TYPE_USERS_IMPORTED,
                '用户导入完成',
                "成功 {$success} 条".($failed > 0 ? "，失败 {$failed} 条（可在导入页下载失败明细）。" : '。'),
                route('users.index'),
            );
        } catch (Throwable $e) {
            Log::warning('导入结果站内通知写入失败', [
                'user_id' => $operator->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** 暂存失败明细并返回下载 token（10 分钟有效，下载时一次性消费） */
    protected function cacheImportErrors(array $rows): string
    {
        $token = Str::random(40);
        Cache::put(self::IMPORT_ERRORS_PREFIX.$token, $rows, now()->addMinutes(10));

        return $token;
    }

    /**
     * 是否为「最后一个启用的管理员」。
     *
     * 用于保护：最后一个可登录的 admin 不允许被删除、停用或移除 admin 角色，
     * 避免系统进入无人可管理的状态。
     */
    protected function isLastActiveAdmin(User $user): bool
    {
        if (! $user->isAdmin() || ! $user->isActive()) {
            return false;
        }

        return User::query()
            ->role(User::ROLE_ADMIN)
            ->where('status', 1)
            ->count() <= 1;
    }
}
