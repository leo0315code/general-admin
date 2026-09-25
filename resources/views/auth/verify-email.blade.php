<x-guest-layout>
    <x-slot name="title">验证邮箱</x-slot>

    {{-- 邮箱验证提示页（VerifyEmailPage.vue）--}}
    <div class="card p-8 sm:p-10">
        <div
            data-vue-app
            data-component="verify-email-page"
            data-props='{!! vue_props([
                'sendUrl' => route('verification.send'),
                'logoutUrl' => route('logout'),
                'csrf' => csrf_token(),
                'statusSent' => session('status') === 'verification-link-sent',
            ]) !!}'
        ></div>
    </div>
</x-guest-layout>
