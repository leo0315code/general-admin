<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * 创建用户请求校验
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => '请输入用户名。',
            'name.unique' => '该用户名已被使用。',
            'email.required' => '请输入邮箱。',
            'email.email' => '邮箱格式不正确。',
            'email.unique' => '该邮箱已被使用。',
            'password.required' => '请输入密码。',
            'password.confirmed' => '两次输入的密码不一致。',
        ];
    }
}
