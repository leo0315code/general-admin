<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 批量操作骨架
 *
 * 背景：批量删除 / 批量启停此前在 PostController 与 UserController 各写一份，
 * 骨架完全一致——`Gate::authorize` → ID 白名单清洗 → DB::transaction →
 * 逐条 find（找不到跳过）→ 策略判定（无权/受限计入 skipped）→ 执行动作 →
 * 计数 → 拼提示消息。差异只有三处：取模型、跳过策略、执行动作。
 *
 * 这里把骨架固化，控制器只传三个闭包 + 拼自己的文案。
 *
 * 用法：
 *   ['done' => $deleted, 'skipped' => $skipped] = (new BulkAction($request))->run(
 *       fn (int $id) => Post::query()->find($id),                        // 取模型
 *       fn (Post $post) => ! $request->user()->can('delete', $post),     // 是否跳过
 *       fn (Post $post) => $post->delete(),                              // 执行动作
 *   );
 */
final class BulkAction
{
    public function __construct(private readonly Request $request) {}

    /**
     * ID 白名单清洗：仅保留正整数，去重。
     *
     * 只放行「整型」或「纯数字字符串」——原先的 is_numeric() 会放过 '7.5'
     * 这类浮点串，再被 (int) 静默截断成 7，属于隐患（ids 本应来自勾选框的整数）。
     *
     * @return list<int>
     */
    public function ids(): array
    {
        $ids = $this->request->input('ids', []);

        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->filter(fn ($id) => is_int($id) ? $id > 0 : is_string($id) && ctype_digit($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 事务内逐条执行
     *
     * 找不到的 id 静默忽略（不计入任何计数）；被 $skip 判定的计入 skipped。
     * 整体包一个事务：任一条失败整批回滚，不留半截状态。
     *
     * @param  callable(int): mixed  $resolver  按 id 取模型，找不到返回 null
     * @param  callable(mixed): bool  $skip  true 表示跳过（计入 skipped）
     * @param  callable(mixed): void  $perform  实际动作
     * @return array{done: int, skipped: int}
     */
    public function run(callable $resolver, callable $skip, callable $perform): array
    {
        $done = 0;
        $skipped = 0;

        DB::transaction(function () use ($resolver, $skip, $perform, &$done, &$skipped) {
            foreach ($this->ids() as $id) {
                $model = $resolver($id);

                if (! $model) {
                    continue;
                }

                if ($skip($model)) {
                    $skipped++;

                    continue;
                }

                $perform($model);
                $done++;
            }
        });

        return ['done' => $done, 'skipped' => $skipped];
    }
}
