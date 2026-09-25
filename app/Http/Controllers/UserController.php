<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Exports\UsersImportTemplate;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Imports\UsersImport;
use App\Models\User;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

/**
 * 用户管理控制器（基于 spatie/laravel-permission）
 *
 * 列表（分页+搜索）、创建、编辑、删除（软删除）、分配角色、重置密码。
 */
class UserController extends Controller
{
    /** 每页显示数量 */
    protected const PER_PAGE = 15;

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
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
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

        return redirect()
            ->route('users.edit', $user)
            ->with('success', "用户「{$user->name}」的密码已重置，下次登录需修改密码。");
    }

    /** 切换账号启停状态（禁止停用自己；最后一个启用 admin 不可停用） */
    public function toggleStatus(User $user): RedirectResponse
    {
        Gate::authorize('user.manage');

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

    /** 批量删除用户（软删除；跳过自己与最后一个启用的 admin） */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        Gate::authorize('user.manage');

        $deleted = 0;
        $skipped = 0;

        foreach ($this->validatedIds($request) as $id) {
            $user = User::query()->find($id);

            if (! $user) {
                continue;
            }

            if ($user->is(auth()->user()) || $this->isLastActiveAdmin($user)) {
                $skipped++;

                continue;
            }

            $user->delete();
            $deleted++;
        }

        $message = "已删除 {$deleted} 个用户。";

        if ($skipped > 0) {
            $message .= " 跳过 {$skipped} 个受限项（自己或最后一个启用的管理员）。";
        }

        return back()->with('success', $message);
    }

    /** 批量切换账号启停状态（跳过自己与最后一个启用的 admin） */
    public function bulkToggleStatus(Request $request): RedirectResponse
    {
        Gate::authorize('user.manage');

        $changed = 0;
        $skipped = 0;

        foreach ($this->validatedIds($request) as $id) {
            $user = User::query()->find($id);

            if (! $user) {
                continue;
            }

            if ($user->is(auth()->user())) {
                $skipped++;

                continue;
            }

            if ($user->isActive() && $this->isLastActiveAdmin($user)) {
                $skipped++;

                continue;
            }

            $user->update(['status' => ! $user->isActive()]);
            $changed++;
        }

        $message = "已更新 {$changed} 个用户的状态。";

        if ($skipped > 0) {
            $message .= " 跳过 {$skipped} 个受限项（自己或最后一个启用的管理员）。";
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
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
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
        Gate::authorize('user.manage');

        $user = User::query()->onlyTrashed()->findOrFail($id);
        $user->restore();

        return back()
            ->with('success', "用户「{$user->name}」已还原，可正常登录。");
    }

    /** 彻底删除用户（不可恢复） */
    public function forceDestroy(int $id): RedirectResponse
    {
        Gate::authorize('user.manage');

        $user = User::query()->onlyTrashed()->findOrFail($id);
        $name = $user->name;
        $user->forceDelete();

        return back()
            ->with('success', "用户「{$name}」已彻底删除，无法恢复。");
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
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'file.required' => '请选择要导入的文件。',
            'file.mimes' => '仅支持 xlsx / xls 格式。',
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

        return back()
            ->with('success', $message)
            ->with('import_errors', $errors);
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
