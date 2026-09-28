<?php

namespace Tests\Unit;

use App\Support\Captcha;
use Tests\TestCase;

/**
 * 登录验证码（纯逻辑，无 DB 依赖）
 *
 * 关键语义：verify() 是一次性的——无论成功失败都消费掉 session 中的码，
 * 防止同一个验证码被反复尝试（爆破）。
 */
class CaptchaTest extends TestCase
{
    public function test_generate_stores_four_digit_code_in_session(): void
    {
        $code = Captcha::generate();

        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
        $this->assertSame($code, session()->get(Captcha::SESSION_KEY));
    }

    public function test_verify_passes_with_correct_code(): void
    {
        $code = Captcha::generate();

        $this->assertTrue(Captcha::verify($code));
    }

    public function test_verify_is_one_time_only(): void
    {
        $code = Captcha::generate();

        $this->assertTrue(Captcha::verify($code));
        // 第二次使用同一个码必须失败（已被消费）
        $this->assertFalse(Captcha::verify($code));
    }

    public function test_verify_fails_with_wrong_code(): void
    {
        Captcha::generate();

        // generate() 范围是 1000-9999，故 0000 必然是错误的
        $this->assertFalse(Captcha::verify('0000'));
    }

    public function test_verify_consumes_session_even_when_wrong(): void
    {
        $code = Captcha::generate();

        $this->assertFalse(Captcha::verify('9999'));
        // 失败也消费：防止用错误码反复试探
        $this->assertFalse(Captcha::verify($code));
        $this->assertNull(session()->get(Captcha::SESSION_KEY));
    }

    public function test_verify_fails_when_no_code_generated(): void
    {
        session()->forget(Captcha::SESSION_KEY);

        $this->assertFalse(Captcha::verify('1234'));
    }

    public function test_verify_fails_on_null_input(): void
    {
        Captcha::generate();

        $this->assertFalse(Captcha::verify(null));
    }

    public function test_svg_returns_markup_and_stores_new_code(): void
    {
        $svg = Captcha::svg();

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringEndsWith('</svg>', $svg);

        // 数字逐字符渲染，且干扰线存在
        $this->assertMatchesRegularExpression('/<line /', $svg);
        $this->assertMatchesRegularExpression('/<text /', $svg);

        // svg() 会重新生成并写入 session
        $this->assertNotNull(session()->get(Captcha::SESSION_KEY));
    }
}
