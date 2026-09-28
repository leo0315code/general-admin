<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * 全局 helper（纯函数，不启动 Laravel 应用）
 *
 * 重点是 vue_props() 的转义语义——服务端把业务数据注入 Vue 根节点的
 * data-props 属性，一旦转义不到位就是属性逃逸型 XSS。
 */
class HelpersTest extends TestCase
{
    public function test_vue_props_keeps_chinese_readable(): void
    {
        $json = vue_props(['title' => '用户管理']);

        $this->assertStringContainsString('用户管理', $json, '中文应保留原样，便于 SSR 断言与爬虫读取');
        $this->assertSame('用户管理', json_decode($json, true)['title']);
    }

    public function test_vue_props_escapes_html_special_chars(): void
    {
        $json = vue_props(['html' => '<script>alert(1)</script>']);

        // < > 被 JSON_HEX_TAG 转为 \u003C \u003E，属性内无法闭合标签
        $this->assertStringNotContainsString('<script>', $json);
        $this->assertStringContainsString('\u003Cscript\u003E', $json);
    }

    public function test_vue_props_escapes_quotes_and_ampersand(): void
    {
        $json = vue_props(['v' => 'a"b\'c&d']);

        $this->assertStringNotContainsString('"b', $json, '双引号必须转义，否则提前闭合属性');
        $this->assertStringContainsString('\u0022', $json);
        $this->assertStringContainsString('\u0027', $json);
        $this->assertStringContainsString('\u0026', $json);
    }

    public function test_vue_props_does_not_escape_slashes(): void
    {
        $json = vue_props(['url' => '/users/1']);

        $this->assertStringContainsString('/users/1', $json);
    }

    public function test_vue_props_output_is_valid_json(): void
    {
        $payload = [
            'id' => 1,
            'name' => '张三',
            'roles' => ['admin', 'editor'],
            'nested' => ['a' => ['b' => 1]],
        ];

        $this->assertSame($payload, json_decode(vue_props($payload), true));
    }

    public function test_dict_helper_is_registered(): void
    {
        $this->assertTrue(function_exists('dict'));
        $this->assertTrue(function_exists('vue_props'));
    }
}
