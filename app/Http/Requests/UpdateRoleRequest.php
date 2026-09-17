<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 更新角色请求校验（name 唯一性忽略当前角色；内置 admin 角色标识不可修改）
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('role.manage') ?? false;
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
                Rule::unique('roles', 'name')->ignore($this->route('role')->id),
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
