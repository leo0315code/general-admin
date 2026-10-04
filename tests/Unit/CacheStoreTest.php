<?php

namespace Tests\Unit;

use App\Support\CacheStore;
use Illuminate\Support\Facades\Config;
use Predis\Client;
use Tests\TestCase;

class CacheStoreTest extends TestCase
{
    public function test_name_follows_default_cache_store(): void
    {
        Config::set('cache.default', 'database');

        $this->assertSame('database', CacheStore::name());
    }

    /** array / null 不跨进程共享：限流计数各算各的，等于没限流 */
    public function test_unshared_drivers_are_not_usable(): void
    {
        Config::set('cache.default', 'array');
        $this->assertFalse(CacheStore::usable());
        $this->assertStringContainsString('等于没限流', (string) CacheStore::problem());

        Config::set('cache.default', 'null');
        $this->assertFalse(CacheStore::usable());
    }

    public function test_shared_drivers_are_usable(): void
    {
        foreach (['redis', 'database', 'file'] as $driver) {
            Config::set('cache.default', $driver);

            $this->assertTrue(CacheStore::usable(), $driver.' 属于跨进程共享驱动');
        }
    }

    /** database 能用但代价高：只给提示，不构成阻断 */
    public function test_database_store_reports_a_cost_hint(): void
    {
        Config::set('cache.default', 'database');

        $problem = CacheStore::problem();

        $this->assertNotNull($problem);
        $this->assertStringContainsString('建议改 redis', (string) $problem);
    }

    /** 指向一个没有服务监听的端口，必须被识别为「连不上」而不是静默通过 */
    public function test_redis_store_reports_connection_failure(): void
    {
        Config::set('cache.default', 'redis');
        Config::set('cache.stores.redis.connection', 'cache');
        Config::set('database.redis.cache.port', '6399'); // 本机不监听，连接必失败
        Config::set('database.redis.cache.max_retries', 1);

        $problem = CacheStore::problem();

        $this->assertNotNull($problem, 'redis 连不上时必须给出说明，否则登录限流会直接 500');
        $this->assertStringContainsString('连不上', (string) $problem);
    }

    public function test_redis_client_availability_matches_real_extension(): void
    {
        $this->assertSame(
            extension_loaded('redis') || class_exists(Client::class),
            CacheStore::redisClientAvailable(),
        );
    }
}
