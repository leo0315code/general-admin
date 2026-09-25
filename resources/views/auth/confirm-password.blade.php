<x-guest-layout>
    <x-slot name="title">确认密码</x-slot>

    {{-- 安全区域密码确认（ConfirmPasswordForm.vue）--}}
    <div class="card p-8 sm:p-10">
        <x-vue-mount
            component="confirm-password-form"
            :props="[
                'action' => route('password.confirm'),
                'csrf' => csrf_token(),
                'errors' => $errors->toArray(),
            ]"
        />
    </div>
</x-guest-layout>
