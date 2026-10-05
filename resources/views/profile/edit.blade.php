<x-app-layout>
    <x-slot name="header">
        <x-page-header title="个人资料" description="管理账号基本信息、登录密码与账号注销" />
    </x-slot>

    <x-flash-messages />

    @php
        $profileProps = [
            'csrf' => csrf_token(),
            'user' => [
                'name' => old('name', $user->name),
                // 邮箱选填：提交留空时回填当前邮箱（语义为「不修改邮箱」）
                'email' => old('email') ?: $user->email,
            ],
            'unverified' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail(),
            'updateUrl' => route('profile.update'),
            'passwordUrl' => route('password.update'),
            'destroyUrl' => route('profile.destroy'),
            'verificationUrl' => route('verification.send'),
            // 登录设备（仅本人可见）
            'sessions' => $sessions,
            'sessionsSupported' => $sessionsSupported,
            'sessionsDestroyUrl' => route('profile.sessions.destroy', ['session' => '__ID__']),
            'sessionsDestroyOthersUrl' => route('profile.sessions.destroy-others'),
            'errors' => [
                'default' => $errors->getMessages(),
                'updatePassword' => $errors->updatePassword->getMessages(),
                'userDeletion' => $errors->userDeletion->getMessages(),
            ],
            'status' => session('status', ''),
        ];
    @endphp

    <x-vue-mount component="profile-form" :props="$profileProps" />
</x-app-layout>
