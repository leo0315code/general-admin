<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * 冒烟测试：根路径为公开欢迎页（不跳登录页），未登录访问后台则跳转到 /admin/login。
     */
    public function test_root_path_shows_public_welcome_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('现代化通用管理后台');
    }

    public function test_guests_accessing_admin_are_redirected_to_admin_login(): void
    {
        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }
}
