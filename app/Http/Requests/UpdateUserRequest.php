<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * 更新用户请求校验（邮箱唯一性忽略当前用户）
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('user.manage') ?? false;
    }

    /**
     * 邮箱选填：留空归一为 null（管理员显式清空邮箱 / 表单未填时避免空串绕过校验）
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'name')->ignore($this->route('user')->id)->whereNull('deleted_at'),
            ],
            // 邮箱选填：留空表示清空该用户邮箱
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')->id)->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
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
            'password.min' => '密码至少需要 10 个字符，且需同时包含字母与数字。',
            'password.letters' => '密码需包含字母。',
            'password.numbers' => '密码需包含数字。',
            'password.confirmed' => '两次输入的密码不一致。',
        ];
    }
}
