<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'sessions' => Sessions::forUser($request->user()->id, $request->session()->getId()),
            'sessionsSupported' => Sessions::databaseDriven(),
        ]);
    }

    /** 踢掉某一条登录设备（会话）；当前这条不可通过此入口删除，防止误操作 */
    public function destroySession(Request $request, string $session): RedirectResponse
    {
        if ($session === $request->session()->getId()) {
            return back()->with('error', '不能踢掉当前正在使用的会话；如需退出请直接登出。');
        }

        $kicked = Sessions::invalidate($request->user()->id, $session);

        return back()->with(
            $kicked ? 'success' : 'error',
            $kicked ? '该设备已下线。' : '会话不存在或已下线。'
        );
    }

    /** 踢掉其它全部登录设备，保留当前这条 */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $kicked = Sessions::invalidateOthers($request->user()->id, $request->session()->getId());

        return back()->with('success', "已踢掉其它 {$kicked} 台设备的会话。");
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // 邮箱选填：留空表示「不修改邮箱」，直接剔除该字段（避免把现有邮箱清空）
        $data = $request->validated();
        if (($data['email'] ?? null) === null) {
            unset($data['email']);
        }

        $request->user()->fill($data);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
