<?php

namespace App\Support;

/**
 * User-Agent 粗解析
 *
 * 只提取「浏览器 + 平台」两级粗标签用于展示（登录设备、登录记录页），
 * 不做精确版本号识别——那种需求才引 jenssegers/agent 之类的库，
 * 为展示一个「Chrome · Windows」字符串引一个库不值得。
 *
 * 匹配顺序敏感（如 Edge 的 UA 同时含 Chrome，必须先判 Edge）：
 * 一律「先具体后宽泛」，新浏览器往前插。
 */
class UserAgent
{
    /** @return array{browser:string,platform:string} */
    public static function parse(string $ua): array
    {
        return [
            'browser' => self::browser($ua),
            'platform' => self::platform($ua),
        ];
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            $ua === '' => '未知浏览器',
            str_contains($ua, 'Edg/'), str_contains($ua, 'Edge/') => 'Edge',
            str_contains($ua, 'OPR/'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung',
            str_contains($ua, 'MicroMessenger') => '微信',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome') => 'Safari',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'MSIE'), str_contains($ua, 'Trident/') => 'IE',
            default => '其它浏览器',
        };
    }

    private static function platform(string $ua): string
    {
        return match (true) {
            $ua === '' => '未知设备',
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X'), str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            default => '其它设备',
        };
    }
}
