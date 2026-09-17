<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * 新建菜单 / 权限节点请求校验
 */
class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('menu.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'pid' => ['required', 'integer', 'min:0'],
            'type' => ['required', Rule::in(array_keys(Menu::TYPE_LABELS))],
            'title' => ['required', 'string', 'max:50'],
            'permission_name' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9._-]*$/',
                Rule::unique('menus', 'permission_name'),
            ],
            'icon' => ['nullable', 'string', 'max:50'],
            'route' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->integer('pid') > 0) {
            $rules['pid'][] = Rule::exists('menus', 'id');
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // 路由名必须是真实存在的路由，避免菜单点开 404
            if ($this->filled('route') && ! Route::has((string) $this->input('route'))) {
                $validator->errors()->add('route', '路由名称不存在，请填写真实路由名（如 users.index）。');
            }

            // 按钮权限必须挂在菜单下才有意义
            if ($this->input('type') === Menu::TYPE_BUTTON && $this->integer('pid') === 0) {
                $validator->errors()->add('pid', '按钮权限必须挂在某个菜单下。');
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'title.required' => '请输入菜单名称。',
            'type.required' => '请选择节点类型。',
            'type.in' => '节点类型不合法。',
            'pid.required' => '请选择上级节点。',
            'pid.exists' => '所选上级节点不存在。',
            'permission_name.regex' => '权限标识只能包含小写字母、数字、点、下划线与连字符，且须以小写字母开头。',
            'permission_name.unique' => '该权限标识已被占用。',
            'sort.integer' => '排序必须为数字。',
        ];
    }
}
