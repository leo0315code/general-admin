<?php

use App\Support\Dict;

if (! function_exists('dict')) {
    /**
     * 数据字典读取（value 省略时返回该类型全部启用项，否则返回单项中文名）
     *
     * dict('post_status')                    → [draft => 草稿, published => 已发布, ...]
     * dict('post_status', 'draft')           → '草稿'
     * dict('post_status', 'nope', '未知')    → '未知'
     */
    function dict(string $type, string|int|null $value = null, ?string $default = null): mixed
    {
        if ($value === null || $value === '') {
            return Dict::options($type);
        }

        return Dict::label($type, $value, $default);
    }
}
