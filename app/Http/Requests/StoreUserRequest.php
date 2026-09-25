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

    /**
     * 邮箱选填：前端留空 → 归一为 null（避免空串触发 email 格式校验与唯一索引冲突）
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email') && trim((string) $this->input('email')) === '') {
            $this->merge(['email' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:users,name'],
            // 邮箱选填（users.email 已改为 nullable）：留空表示该用户无邮箱
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
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
            'email.email' => '邮箱格式不正确。',
            'email.unique' => '该邮箱已被使用。',
            'password.required' => '请输入密码。',
            'password.confirmed' => '两次输入的密码不一致。',
        ];
    }
}
