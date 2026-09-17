<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 清空 Spatie 权限缓存，避免 RefreshDatabase 事务回滚后残留的权限 ID 造成误判
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
