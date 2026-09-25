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

if (! function_exists('vue_props')) {
    /**
     * 输出 Vue 挂载根节点的 data-props JSON 属性值。
     *
     * 与 @json 的差异：保留中文原样（JSON_UNESCAPED_UNICODE），
     * 使标题等业务文本仍出现在 HTML 中，服务端渲染断言/爬虫可读。
     * 保留 HTML 特殊字符转义（< > & ' "）防属性逃逸与 XSS。
     *
     * 用法：<div data-vue-app data-component="x" data-props='{!! vue_props($props) !!}' x-ignore></div>
     */
    function vue_props(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        );
    }
}
