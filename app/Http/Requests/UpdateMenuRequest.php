<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * 编辑菜单 / 权限节点请求校验
 *
 * 在 StoreMenuRequest 基础上：
 * - 权限标识唯一性忽略自身
 * - 禁止把自身或自己的子节点选为父级（避免形成环）
 */
class UpdateMenuRequest extends StoreMenuRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        $menu = $this->route('menu');

        $rules['permission_name'] = [
            'nullable',
            'string',
            'max:100',
            'regex:/^[a-z][a-z0-9._-]*$/',
            Rule::unique('menus', 'permission_name')->ignore($menu?->id),
        ];

        $rules['pid'][] = function (string $attribute, mixed $value, \Closure $fail) use ($menu): void {
            if ($menu && in_array((int) $value, $menu->descendantIds(), true)) {
                $fail('不能选择自身或其子节点作为上级节点。');
            }
        };

        return $rules;
    }
}
