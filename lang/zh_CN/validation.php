<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 验证语言行
    |--------------------------------------------------------------------------
    |
    | 以下语言行包含验证器使用的默认错误消息。
    | 可根据需要修改为更贴合业务的中文表述。
    |
    */

    'accepted' => ':attribute 必须被接受。',
    'accepted_if' => '当 :other 为 :value 时，:attribute 必须被接受。',
    'active_url' => ':attribute 不是有效的网址。',
    'after' => ':attribute 必须晚于 :date。',
    'after_or_equal' => ':attribute 必须晚于或等于 :date。',
    'alpha' => ':attribute 只能包含字母。',
    'alpha_dash' => ':attribute 只能包含字母、数字、短横线和下划线。',
    'alpha_num' => ':attribute 只能包含字母和数字。',
    'array' => ':attribute 必须是数组。',
    'before' => ':attribute 必须早于 :date。',
    'before_or_equal' => ':attribute 必须早于或等于 :date。',
    'between' => [
        'array' => ':attribute 必须包含 :min 到 :max 项。',
        'file' => ':attribute 大小必须在 :min 到 :max KB 之间。',
        'numeric' => ':attribute 必须在 :min 到 :max 之间。',
        'string' => ':attribute 长度必须在 :min 到 :max 个字符之间。',
    ],
    'boolean' => ':attribute 必须是 true 或 false。',
    'confirmed' => ':attribute 确认不匹配。',
    'current_password' => '密码不正确。',
    'date' => ':attribute 不是有效的日期。',
    'date_equals' => ':attribute 必须等于 :date。',
    'date_format' => ':attribute 必须匹配格式 :format。',
    'declined' => ':attribute 必须被拒绝。',
    'declined_if' => '当 :other 为 :value 时，:attribute 必须被拒绝。',
    'different' => ':attribute 和 :other 必须不同。',
    'digits' => ':attribute 必须是 :digits 位数字。',
    'digits_between' => ':attribute 必须是 :min 到 :max 位数字。',
    'dimensions' => ':attribute 图片尺寸无效。',
    'distinct' => ':attribute 有重复值。',
    'email' => ':attribute 必须是有效的邮箱地址。',
    'ends_with' => ':attribute 必须以 :values 之一结尾。',
    'exists' => '所选 :attribute 无效。',
    'file' => ':attribute 必须是文件。',
    'filled' => ':attribute 不能为空。',
    'gt' => [
        'array' => ':attribute 必须多于 :value 项。',
        'file' => ':attribute 必须大于 :value KB。',
        'numeric' => ':attribute 必须大于 :value。',
        'string' => ':attribute 必须大于 :value 个字符。',
    ],
    'gte' => [
        'array' => ':attribute 必须大于等于 :value 项。',
        'file' => ':attribute 必须大于等于 :value KB。',
        'numeric' => ':attribute 必须大于等于 :value。',
        'string' => ':attribute 必须大于等于 :value 个字符。',
    ],
    'image' => ':attribute 必须是图片。',
    'in' => '所选 :attribute 无效。',
    'in_array' => ':attribute 不存在于 :other 中。',
    'integer' => ':attribute 必须是整数。',
    'ip' => ':attribute 必须是有效的 IP 地址。',
    'ipv4' => ':attribute 必须是有效的 IPv4 地址。',
    'ipv6' => ':attribute 必须是有效的 IPv6 地址。',
    'json' => ':attribute 必须是有效的 JSON 字符串。',
    'lt' => [
        'array' => ':attribute 必须少于 :value 项。',
        'file' => ':attribute 必须小于 :value KB。',
        'numeric' => ':attribute 必须小于 :value。',
        'string' => ':attribute 必须小于 :value 个字符。',
    ],
    'lte' => [
        'array' => ':attribute 不能超过 :value 项。',
        'file' => ':attribute 不能超过 :value KB。',
        'numeric' => ':attribute 不能超过 :value。',
        'string' => ':attribute 不能超过 :value 个字符。',
    ],
    'max' => [
        'array' => ':attribute 不能超过 :max 项。',
        'file' => ':attribute 不能超过 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],
    'mimes' => ':attribute 必须是 :values 类型的文件。',
    'mimetypes' => ':attribute 必须是 :values 类型的文件。',
    'min' => [
        'array' => ':attribute 至少包含 :min 项。',
        'file' => ':attribute 至少 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 至少 :min 个字符。',
    ],
    'multiple_of' => ':attribute 必须是 :value 的倍数。',
    'not_in' => '所选 :attribute 无效。',
    'not_regex' => ':attribute 格式不正确。',
    'numeric' => ':attribute 必须是数字。',
    'password' => [
        'letters' => ':attribute 必须包含至少一个字母。',
        'mixed' => ':attribute 必须包含至少一个大写字母和一个小写字母。',
        'numbers' => ':attribute 必须包含至少一个数字。',
        'symbols' => ':attribute 必须包含至少一个符号。',
        'uncompromised' => '给定的 :attribute 已出现在数据泄露中，请选择其他 :attribute。',
    ],
    'present' => ':attribute 必须存在。',
    'prohibited' => ':attribute 禁止使用。',
    'prohibited_if' => '当 :other 为 :value 时，禁止使用 :attribute。',
    'prohibited_unless' => '除非 :other 为 :values，否则禁止使用 :attribute。',
    'prohibits' => ':attribute 禁止与 :other 同时使用。',
    'regex' => ':attribute 格式不正确。',
    'required' => ':attribute 为必填项。',
    'required_array_keys' => ':attribute 必须包含 :values 键。',
    'required_if' => '当 :other 为 :value 时，:attribute 为必填项。',
    'required_unless' => '除非 :other 为 :values，否则 :attribute 为必填项。',
    'required_with' => '当 :values 存在时，:attribute 为必填项。',
    'required_with_all' => '当 :values 都存在时，:attribute 为必填项。',
    'required_without' => '当 :values 不存在时，:attribute 为必填项。',
    'required_without_all' => '当 :values 都不存在时，:attribute 为必填项。',
    'same' => ':attribute 与 :other 必须一致。',
    'size' => [
        'array' => ':attribute 必须包含 :size 项。',
        'file' => ':attribute 大小必须是 :size KB。',
        'numeric' => ':attribute 必须是 :size。',
        'string' => ':attribute 长度必须是 :size 个字符。',
    ],
    'starts_with' => ':attribute 必须以 :values 之一开头。',
    'string' => ':attribute 必须是字符串。',
    'timezone' => ':attribute 必须是有效的时区。',
    'unique' => ':attribute 已被占用。',
    'uploaded' => ':attribute 上传失败。',
    'url' => ':attribute 格式不正确。',
    'uuid' => ':attribute 必须是有效的 UUID。',

    /*
    |--------------------------------------------------------------------------
    | 自定义验证语言行
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 自定义属性名
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => '姓名',
        'email' => '邮箱',
        'password' => '密码',
        'new_password' => '新密码',
        'title' => '标题',
        'content' => '内容',
        'status' => '状态',
        'published_at' => '发布时间',
        'slug' => '标识',
        'description' => '描述',
        'roles' => '角色',
        'permissions' => '权限',
    ],
];
