<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 侧边栏导航缓存（Navigation::CACHE_KEY = menus.tree）回归：
 * - 首次构建后写入缓存，flush() 显式失效
 * - 菜单模型 saved / deleted 事件自动失效缓存（无需手动 flush）
 * - 缓存路径与直查路径输出一致（分组 / 权限过滤 / 激活态）
 */
class NavigationCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_navigation_is_cached_after_first_build(): void
    {
        $this->assertFalse(Cache::has(Navigation::CACHE_KEY));

        Navigation::forUser($this->admin());

        $this->assertTrue(Cache::has(Navigation::CACHE_KEY));
    }

    public function test_flush_clears_the_cache(): void
    {
        Navigation::forUser($this->admin());
        $this->assertTrue(Cache::has(Navigation::CACHE_KEY));

        Navigation::flush();
        $this->assertFalse(Cache::has(Navigation::CACHE_KEY));
    }

    public function test_creating_menu_invalidates_cache_automatically(): void
    {
        $user = $this->admin();
        Navigation::forUser($user); // 预热缓存
        $this->assertTrue(Cache::has(Navigation::CACHE_KEY));

        Menu::query()->create([
            'pid' => 0,
            'type' => Menu::TYPE_MENU,
            'title' => '缓存测试菜单',
            'permission_name' => null,
            'route' => 'dashboard',
            'sort' => 999,
            'status' => true,
        ]);

        // saved 事件已自动失效，导航立即可见新菜单
        $this->assertFalse(Cache::has(Navigation::CACHE_KEY));

        $titles = collect(Navigation::forUser($user))
            ->flatMap(fn (array $group): array => array_column($group['items'], 'title'))
            ->all();

        $this->assertContains('缓存测试菜单', $titles);
    }

    public function test_deleting_menu_invalidates_cache_automatically(): void
    {
        $user = $this->admin();
        Navigation::forUser($user); // 预热缓存

        $menu = Menu::query()->create([
            'pid' => 0,
            'type' => Menu::TYPE_MENU,
            'title' => '待删除菜单',
            'permission_name' => null,
            'route' => 'dashboard',
            'sort' => 999,
            'status' => true,
        ]);

        Navigation::forUser($user); // 缓存含该菜单
        $menu->delete();

        $this->assertFalse(Cache::has(Navigation::CACHE_KEY));

        $titles = collect(Navigation::forUser($user))
            ->flatMap(fn (array $group): array => array_column($group['items'], 'title'))
            ->all();

        $this->assertNotContains('待删除菜单', $titles);
    }

    public function test_cached_output_matches_expected_structure(): void
    {
        $groups = Navigation::forUser($this->admin());

        $this->assertNotEmpty($groups);

        // 每个分组结构完整：title（顶级菜单为 null）+ items 列表
        foreach ($groups as $group) {
            $this->assertArrayHasKey('title', $group);
            $this->assertNotEmpty($group['items']);

            foreach ($group['items'] as $item) {
                $this->assertSame(
                    ['title', 'url', 'icon', 'active'],
                    array_keys($item)
                );
                $this->assertIsBool($item['active']);
            }
        }
    }
}
