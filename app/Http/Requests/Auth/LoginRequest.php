<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * 登录标识字段为 username（兼容用户名或邮箱两种方式，国内习惯用户名登录）。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'captcha' => ['required', 'string', 'max:8'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * 输入为邮箱格式时按邮箱匹配，否则按用户名（name）匹配。
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // 校验验证码（一次性，校验后清除）
        if (! \App\Support\Captcha::verify($this->string('captcha'))) {
            \App\Support\OperationLogger::log(
                null,
                $this->string('username'),
                'POST',
                '登录失败',
                '验证码错误',
                '登录',
                $this->ip(),
                substr((string) $this->userAgent(), 0, 500)
            );

            throw ValidationException::withMessages([
                'captcha' => '验证码错误或已过期，请重新输入。',
            ]);
        }

        $username = (string) $this->input('username');

        $field = filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $credentials = [
            $field => $username,
            'password' => (string) $this->input('password'),
        ];

        // 停用校验：账号 status=0 不允许登录（即使密码正确）
        $account = \App\Models\User::query()
            ->where($field, $username)
            ->first();

        if ($account && ! $account->isActive()) {
            \App\Support\OperationLogger::log(
                $account->id,
                $account->name,
                'POST',
                '登录失败',
                '账号已停用',
                '登录',
                $this->ip(),
                substr((string) $this->userAgent(), 0, 500)
            );

            throw ValidationException::withMessages([
                'username' => '该账号已被停用，请联系管理员。',
            ]);
        }

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            \App\Support\OperationLogger::log(
                null,
                $username,
                'POST',
                '登录失败',
                '用户名或密码错误',
                '登录',
                $this->ip(),
                substr((string) $this->userAgent(), 0, 500)
            );

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
