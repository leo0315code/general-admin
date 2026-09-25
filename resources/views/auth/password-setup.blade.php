<x-guest-layout>
    <x-slot name="title">设置新密码</x-slot>

    {{-- 首次登录强制改密（PasswordSetupForm.vue）--}}
    <div class="card p-8 sm:p-10">
        <x-vue-mount
            component="password-setup-form"
            :props="[
                'action' => route('password.setup.update'),
                'csrf' => csrf_token(),
                'errors' => $errors->toArray(),
            ]"
        />
    </div>
</x-guest-layout>
