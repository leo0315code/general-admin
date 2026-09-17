<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 创建角色请求校验
 *
 * spatie/laravel-permission 中角色标识即 name 字段（如 admin / editor）。
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(\App\Models\User::ROLE_ADMIN) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z][a-z0-9._-]*$/',
                Rule::unique('roles', 'name'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => '请输入角色标识。',
            'name.regex' => '角色标识只能包含小写字母、数字、点、下划线与连字符，且须以小写字母开头。',
            'name.unique' => '该角色标识已存在。',
        ];
    }
}
