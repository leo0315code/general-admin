<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use App\Exports\UsersImportTemplate;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Imports\UsersImport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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

    /** 用户列表：分页 + 关键字搜索（姓名/邮箱） */
    public function index(Request $request): View
    {
        $keyword = $request->query('search');

        $users = User::query()
            ->with('roles:id,name')
            ->when($keyword, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(config('app.pagination', self::PER_PAGE))
            ->withQueryString();

        return view('users.index', compact('users', 'keyword'));
    }

    /** 创建用户表单 */
    public function create(): View
    {
        $roles = Role::query()->orderBy('id')->get();

        return view('users.create', compact('roles'));
    }

    /** 保存新用户并分配角色 */
    public function store(StoreUserRequest $request): RedirectResponse
    {
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
        $user->load('roles:id,name');
        $roles = Role::query()->orderBy('id')->get();
        $userRoleIds = $user->roles->pluck('id')->all();

        return view('users.edit', compact('user', 'roles', 'userRoleIds'));
    }

    /** 更新用户资料与角色 */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
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
        $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.required' => '请输入新密码。',
            'new_password.min' => '新密码至少需要 8 个字符。',
            'new_password.confirmed' => '两次输入的新密码不一致。',
        ]);

        $user->update(['password' => Hash::make($request->input('new_password'))]);

        return redirect()
            ->route('users.edit', $user)
            ->with('success', "用户「{$user->name}」的密码已重置。");
    }

    /** 删除用户（软删除；禁止删除自己） */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->is(auth()->user())) {
            return redirect()
                ->route('users.index')
                ->with('error', '不能删除当前登录的账号。');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "用户「{$user->name}」已删除（软删除）。");
    }

    /** 导出用户数据（Excel） */
    public function export()
    {
        return Excel::download(new UsersExport, '用户数据-'.date('YmdHis').'.xlsx');
    }

    /** 下载用户导入模板 */
    public function importTemplate()
    {
        return Excel::download(new UsersImportTemplate, '用户导入模板.xlsx');
    }

    /** 批量导入用户（Excel） */
    public function import(Request $request): RedirectResponse
    {
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
}
