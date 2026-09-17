<?php

namespace App\Support;

/**
 * 登录验证码（SVG 生成，无图像库依赖）
 *
 * - generate()：生成 4 位数字码并存入 session（key: login_captcha）
 * - verify()：校验输入并清除（一次性）
 * - svg()：生成带干扰线与随机形变的 SVG 验证码图片
 */
class Captcha
{
    /** session 存储键 */
    public const SESSION_KEY = 'login_captcha';

    /** 生成验证码并存入 session */
    public static function generate(int $length = 4): string
    {
        $code = (string) random_int(10 ** ($length - 1), 10 ** $length - 1);
        session()->put(self::SESSION_KEY, $code);

        return $code;
    }

    /** 校验输入（忽略大小写），校验成功后立即清除（一次性） */
    public static function verify(?string $input): bool
    {
        $expected = session()->pull(self::SESSION_KEY);

        if ($expected === null || $input === null) {
            return false;
        }

        return strtoupper($input) === strtoupper($expected);
    }

    /** 生成 SVG 验证码图片（新码，重置 session） */
    public static function svg(int $length = 4): string
    {
        $code = self::generate($length);
        $width = 120;
        $height = 40;

        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}' viewBox='0 0 {$width} {$height}'>";
        $svg .= "<rect width='{$width}' height='{$height}' rx='8' fill='#f1f5f9'/>";

        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $x1 = random_int(0, $width);
            $y1 = random_int(0, $height);
            $x2 = random_int(0, $width);
            $y2 = random_int(0, $height);
            $color = sprintf('#%06x', random_int(0, 0x999999));
            $svg .= "<line x1='{$x1}' y1='{$y1}' x2='{$x2}' y2='{$y2}' stroke='{$color}' stroke-width='1' opacity='0.6'/>";
        }

        // 数字（随机位置、旋转、颜色）
        for ($i = 0; $i < strlen($code); $i++) {
            $char = $code[$i];
            $x = 18 + $i * 24 + random_int(-3, 3);
            $y = 28 + random_int(-4, 4);
            $angle = random_int(-25, 25);
            $color = sprintf('#%06x', random_int(0x333333, 0x999999));
            $svg .= "<text x='{$x}' y='{$y}' font-family='monospace' font-size='22' font-weight='bold' fill='{$color}' transform='rotate({$angle} {$x} {$y})'>{$char}</text>";
        }

        $svg .= '</svg>';

        return $svg;
    }
}
