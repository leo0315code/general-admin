<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * 列表查询参数解析器（UI 现代化重构 · T02）
 *
 * 统一解析并归一化列表页的 URL query 参数，防注入：
 * - per_page  白名单（config('app.allowed_per_page')，默认 10/20/50/100），非法值回退默认
 * - sort      排序字段白名单（由控制器传入允许列），非法值返回 null（走默认排序）
 * - sort_dir  仅允许 asc / desc，非法值回退 desc
 *
 * 无参数或非法输入一律回退默认，**永不抛错、永不注入**，
 * 保证「控制器无 query 参数时行为与重构前完全一致」。
 */
class ListQuery
{
    /**
     * 一次性解析 [per_page, sort, sort_dir]。
     *
     * @param  list<string>  $allowedColumns  允许排序的字段白名单
     * @param  string  $defaultSort  保留参数（默认排序字段说明，供未来扩展；当前列表默认走 latest()）
     * @return array{0: int, 1: string|null, 2: string} [每页条数, 排序列(null=默认), 排序方向]
     */
    public static function resolve(Request $request, array $allowedColumns, string $defaultSort = 'id'): array
    {
        return [
            self::perPage($request),
            self::sortColumn($request, $allowedColumns, $defaultSort),
            self::sortDir($request),
        ];
    }

    /** 每页条数：白名单内返回，否则回退默认（config('app.pagination', 15)）。 */
    public static function perPage(Request $request): int
    {
        return self::normalizePerPage($request->query('per_page'), (int) config('app.pagination', 15));
    }

    /**
     * 排序列：必须命中白名单，否则返回 null（控制器据此走默认排序）。
     *
     * @param  list<string>  $allowed
     */
    public static function sortColumn(Request $request, array $allowed, string $default): ?string
    {
        $sort = $request->query('sort');

        if (! is_string($sort) || $sort === '' || ! in_array($sort, $allowed, true)) {
            return null;
        }

        return $sort;
    }

    /** 排序方向：仅 asc / desc，其它一律 desc。 */
    public static function sortDir(Request $request): string
    {
        return self::normalizeDir($request->query('sort_dir'));
    }

    /** 每页条数归一化。 */
    protected static function normalizePerPage(mixed $value, int $default): int
    {
        $allowed = (array) config('app.allowed_per_page', [10, 20, 50, 100]);

        if (is_numeric($value)) {
            $value = (int) $value;

            if (in_array($value, $allowed, true)) {
                return $value;
            }
        }

        return $default;
    }

    /** 排序方向二值化。 */
    protected static function normalizeDir(mixed $value): string
    {
        return $value === 'asc' ? 'asc' : 'desc';
    }
}
