<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * 首次登录强制改密
 *
 * 场景：导入用户（密码随机）或管理员重置密码后，用户首次登录必须先设置自己的密码。
 * 逻辑：must_change_password=1 时登录成功即跳转本页；提交后清除标志并放行。
 */
class PasswordSetupController extends Controller
{
    /** 展示改密表单（已改过密则跳回仪表盘） */
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.password-setup');
    }

    /** 提交新密码：更新密码并清除强制改密标志 */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required' => '请输入新密码。',
            'password.min' => '新密码至少需要 8 个字符。',
            'password.confirmed' => '两次输入的密码不一致。',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->string('password')),
            'must_change_password' => false,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', '密码设置成功，可以开始使用了。');
    }
}
