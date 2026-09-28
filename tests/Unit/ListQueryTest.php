<?php

namespace Tests\Unit;

use App\Support\ListQuery;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 列表查询参数解析（纯逻辑，无 DB 依赖）
 *
 * 核心保证：任何非法输入都回退默认值，永不抛错、永不把用户输入拼进 SQL。
 */
class ListQueryTest extends TestCase
{
    /** 构造带 query 参数的请求 */
    private function request(array $query): Request
    {
        return Request::create('/users', 'GET', $query);
    }

    public function test_per_page_falls_back_to_default_when_absent(): void
    {
        $this->assertSame(15, ListQuery::perPage($this->request([])));
    }

    public function test_per_page_accepts_whitelisted_values(): void
    {
        foreach ([10, 20, 50, 100] as $value) {
            $this->assertSame(
                $value,
                ListQuery::perPage($this->request(['per_page' => $value])),
                "白名单内的 {$value} 应原样返回"
            );
        }
    }

    public function test_per_page_rejects_values_outside_whitelist(): void
    {
        // 越界数字、非数字、空串一律回退默认，防止超大分页拖垮数据库
        foreach ([0, 1, 7, 15, 101, 9999, 'abc', '', '-1'] as $value) {
            $this->assertSame(
                15,
                ListQuery::perPage($this->request(['per_page' => $value])),
                '非法 per_page '.var_export($value, true).' 应回退默认 15'
            );
        }
    }

    public function test_per_page_rejects_array_payload(): void
    {
        // 数组注入（?per_page[]=10）不得被当作数字处理
        $this->assertSame(15, ListQuery::perPage($this->request(['per_page' => ['10']])));
    }

    public function test_sort_column_accepts_whitelisted_column(): void
    {
        $this->assertSame('name', ListQuery::sortColumn($this->request(['sort' => 'name']), ['name', 'email'], 'id'));
    }

    public function test_sort_column_rejects_unknown_or_malicious_column(): void
    {
        $malicious = [
            'id; DROP TABLE users--',
            'secret',
            '',
            'NAME', // 大小写不匹配：白名单是严格比较
        ];

        foreach ($malicious as $value) {
            $this->assertNull(
                ListQuery::sortColumn($this->request(['sort' => $value]), ['name', 'email'], 'id'),
                '非法排序列 '.var_export($value, true).' 应返回 null（走默认排序）'
            );
        }
    }

    public function test_sort_column_rejects_array_payload(): void
    {
        $this->assertNull(ListQuery::sortColumn($this->request(['sort' => ['name']]), ['name'], 'id'));
    }

    public function test_sort_dir_only_allows_asc_or_desc(): void
    {
        $this->assertSame('asc', ListQuery::sortDir($this->request(['sort_dir' => 'asc'])));

        foreach (['desc', 'DESC', 'Asc', 'asc; DROP', '', null, ['asc']] as $value) {
            $this->assertSame(
                'desc',
                ListQuery::sortDir($this->request(['sort_dir' => $value])),
                '非 asc 一律回退 desc'
            );
        }
    }

    public function test_resolve_returns_triple_in_one_call(): void
    {
        [$perPage, $sort, $dir] = ListQuery::resolve($this->request([
            'per_page' => 50,
            'sort' => 'email',
            'sort_dir' => 'asc',
        ]), ['name', 'email']);

        $this->assertSame(50, $perPage);
        $this->assertSame('email', $sort);
        $this->assertSame('asc', $dir);
    }

    public function test_resolve_returns_defaults_for_empty_query(): void
    {
        [$perPage, $sort, $dir] = ListQuery::resolve($this->request([]), ['name', 'email']);

        $this->assertSame(15, $perPage);
        $this->assertNull($sort);
        $this->assertSame('desc', $dir);
    }
}
