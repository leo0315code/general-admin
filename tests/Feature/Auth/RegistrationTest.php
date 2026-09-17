<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 后台管理面板不开放注册：
     * 注册页面与注册接口均应返回 404。
     */
    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/'.config('app.admin_prefix').'/register')->assertNotFound();
    }

    public function test_registration_post_is_not_available(): void
    {
        $this->post('/'.config('app.admin_prefix').'/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
