<?php

namespace App\Support;

use App\Models\DictType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * 数据字典读取辅助
 *
 * 提供「按类型取全部启用项」与「按值取中文名」两个核心能力，
 * 结果缓存到 `dict.{type}` 键（永久缓存），字典增删改时由控制器调用 flush() 失效。
 *
 * 用法（配合全局 helper dict()）：
 *   dict('post_status')                    // 全部启用项：[value => label]
 *   dict('post_status', 'draft')           // 单项中文名：'草稿'
 *   dict('post_status', 'draft', '未知')    // 带默认值
 */
class Dict
{
    /** 取某类型的全部启用项（value => label），缓存永久有效直到显式失效 */
    public static function options(string $type): Collection
    {
        // 缓存只存标量数组：config/cache.php 的 serializable_classes=false（安全默认）
        // 禁止对象反序列化，存 Collection 对象会导致读出来是 __PHP_Incomplete_Class
        $cached = Cache::rememberForever("dict.{$type}", function () use ($type) {
            // 类型停用 = 整组字典不可读（业务语义：停用类型即下线该组配置）
            $dictType = DictType::query()->where('type', $type)->where('status', true)->first();

            if (! $dictType) {
                return [];
            }

            return $dictType->items()
                ->where('status', true)
                ->get()
                ->pluck('label', 'value')
                ->all();
        });

        return collect($cached);
    }

    /** 取某类型的单个值对应的中文名，不存在时返回默认值 */
    public static function label(string $type, string|int|null $value, ?string $default = null): ?string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return self::options($type)->get((string) $value, $default);
    }

    /** 失效缓存：指定类型或全部类型（字典数据量小，默认全量失效即可） */
    public static function flush(?string $type = null): void
    {
        if ($type !== null) {
            Cache::forget("dict.{$type}");

            return;
        }

        foreach (DictType::query()->pluck('type') as $typeName) {
            Cache::forget("dict.{$typeName}");
        }
    }
}
