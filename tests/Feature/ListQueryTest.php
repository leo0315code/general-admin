<?php

namespace Tests\Feature;

use App\Support\ListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 列表查询参数解析回归（UI 现代化重构 · T02 / SEC-2）
 *
 * 覆盖：
 * - 无参数 → 默认每页 + 默认排序（null=latest()）+ desc
 * - per_page 白名单（10/20/50/100）与非法值回退
 * - sort 白名单与注入值（如 `evil;drop--`）回退
 * - sort_dir 二值化（仅 asc/desc，非法回退 desc）
 */
class ListQueryTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = ['id', 'name', 'email', 'status', 'created_at', 'last_login_at'];

    private function makeRequest(array $query = []): Request
    {
        return Request::create('/console/users', 'GET', $query);
    }

    public function test_no_params_returns_defaults(): void
    {
        [$perPage, $sort, $dir] = ListQuery::resolve($this->makeRequest(), self::ALLOWED);

        $this->assertSame((int) config('app.pagination', 15), $perPage);
        $this->assertNull($sort, '无排序参数时应返回 null，由控制器走默认 latest()');
        $this->assertSame('desc', $dir);
    }

    public function test_valid_per_page_is_kept(): void
    {
        foreach ([10, 20, 50, 100] as $perPage) {
            [$resolved] = ListQuery::resolve($this->makeRequest(['per_page' => (string) $perPage]), self::ALLOWED);
            $this->assertSame($perPage, $resolved);
        }
    }

    public function test_invalid_per_page_falls_back_to_default(): void
    {
        foreach (['999', 'abc', '-1', '1.5', '50;drop--'] as $bad) {
            [$perPage] = ListQuery::resolve($this->makeRequest(['per_page' => $bad]), self::ALLOWED);
            $this->assertSame((int) config('app.pagination', 15), $perPage, "per_page={$bad} 应回退默认");
        }
    }

    public function test_valid_sort_and_dir_are_kept(): void
    {
        [, $sort, $dir] = ListQuery::resolve(
            $this->makeRequest(['sort' => 'created_at', 'sort_dir' => 'asc']),
            self::ALLOWED
        );

        $this->assertSame('created_at', $sort);
        $this->assertSame('asc', $dir);
    }

    public function test_invalid_sort_falls_back_to_null(): void
    {
        foreach (['evil;drop--', 'created_at);drop--', 'password', 'id asc', ''] as $bad) {
            [, $sort] = ListQuery::resolve($this->makeRequest(['sort' => $bad]), self::ALLOWED);
            $this->assertNull($sort, "sort={$bad} 应回退默认排序");
        }
    }

    public function test_invalid_sort_dir_falls_back_to_desc(): void
    {
        foreach (['evil', 'DESC', '1', 'asc;drop--'] as $bad) {
            [, , $dir] = ListQuery::resolve($this->makeRequest(['sort' => 'id', 'sort_dir' => $bad]), self::ALLOWED);
            $this->assertSame('desc', $dir, "sort_dir={$bad} 应回退 desc");
        }
    }

    public function test_resolve_never_throws_on_weird_input(): void
    {
        [$perPage, $sort, $dir] = ListQuery::resolve(
            $this->makeRequest(['per_page' => ['10'], 'sort' => ['id'], 'sort_dir' => ['asc']]),
            self::ALLOWED
        );

        $this->assertSame((int) config('app.pagination', 15), $perPage);
        $this->assertNull($sort);
        $this->assertSame('desc', $dir);
    }
}
