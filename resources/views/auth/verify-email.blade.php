<x-guest-layout>
    <x-slot name="title">验证邮箱</x-slot>

    {{-- 邮箱验证提示页（VerifyEmailPage.vue）--}}
    <div class="card p-8 sm:p-10">
        <x-vue-mount
            component="verify-email-page"
            :props="[
                'sendUrl' => route('verification.send'),
                'logoutUrl' => route('logout'),
                'csrf' => csrf_token(),
                'statusSent' => session('status') === 'verification-link-sent',
            ]"
        />
    </div>
</x-guest-layout>
