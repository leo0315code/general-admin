<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 后台管理面板不提供忘记密码自助找回：
     * 密码由管理员在「用户管理」中重置，相关页面与接口均应返回 404。
     */
    public function test_forgot_password_screen_is_not_available(): void
    {
        $this->get('/'.config('app.admin_prefix').'/forgot-password')->assertNotFound();
    }

    public function test_forgot_password_post_is_not_available(): void
    {
        $this->post('/'.config('app.admin_prefix').'/forgot-password', [
            'email' => 'test@example.com',
        ])->assertNotFound();
    }

    public function test_reset_password_screen_is_not_available(): void
    {
        $this->get('/'.config('app.admin_prefix').'/reset-password/fake-token')->assertNotFound();
    }
}
