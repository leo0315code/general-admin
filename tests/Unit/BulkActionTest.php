<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\BulkAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

/**
 * 批量操作骨架
 *
 * 固化 PostController / UserController 三个批量方法共用的骨架：
 * ID 白名单清洗 → 事务内逐条执行 → done / skipped 计数。
 */
class BulkActionTest extends TestCase
{
    use RefreshDatabase;

    /** ID 白名单：只留正整数并去重 */
    public function test_ids_are_sanitized(): void
    {
        $action = new BulkAction(Request::create('/', 'POST', [
            'ids' => ['3', 3, 0, -1, 'abc', null, '007', 5],
        ]));

        $this->assertSame([3, 7, 5], $action->ids());
    }

    /** 浮点串不得被静默截断成整数（'7.5' → 7 是此前的隐患） */
    public function test_numeric_strings_are_rejected(): void
    {
        $action = new BulkAction(Request::create('/', 'POST', [
            'ids' => ['7.5', '1e3', ' 4', '+4', 2.9],
        ]));

        $this->assertSame([], $action->ids());
    }

    /** ids 不是数组时返回空，不抛错 */
    public function test_non_array_ids_yield_empty(): void
    {
        $this->assertSame([], (new BulkAction(Request::create('/', 'POST', ['ids' => '3'])))->ids());
        $this->assertSame([], (new BulkAction(Request::create('/', 'POST')))->ids());
    }

    /** 找不到的 id 静默忽略，不计入任何计数 */
    public function test_missing_records_are_ignored(): void
    {
        $action = new BulkAction(Request::create('/', 'POST', ['ids' => [1, 2, 3]]));

        $result = $action->run(
            fn (int $id) => $id === 2 ? null : ['id' => $id],
            fn (array $row) => false,
            fn (array $row) => null,
        );

        $this->assertSame(['done' => 2, 'skipped' => 0], $result);
    }

    /** 被 skip 判定的计入 skipped，不执行 perform */
    public function test_skipped_records_are_counted_and_not_performed(): void
    {
        $action = new BulkAction(Request::create('/', 'POST', ['ids' => [1, 2, 3]]));
        $performed = [];

        $result = $action->run(
            fn (int $id) => ['id' => $id],
            fn (array $row) => $row['id'] === 2,
            function (array $row) use (&$performed) {
                $performed[] = $row['id'];
            },
        );

        $this->assertSame(['done' => 2, 'skipped' => 1], $result);
        $this->assertSame([1, 3], $performed);
    }

    /** perform 抛异常时整批回滚：先删掉的那条也要还回来 */
    public function test_failure_rolls_back_the_whole_batch(): void
    {
        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->all();

        $action = new BulkAction(Request::create('/', 'POST', ['ids' => $ids]));

        try {
            $action->run(
                fn (int $id) => User::query()->find($id),
                fn (User $user) => $user->id === $ids[1], // 跳过第 2 个，验证它也没被波及
                function (User $user) use ($ids) {
                    $user->delete();

                    if ($user->id === $ids[2]) {
                        throw new RuntimeException('boom');
                    }
                },
            );
            $this->fail('异常应向外传播');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        // 事务回滚：3 条都还在（含已执行过 delete 的第 1 条）
        $this->assertSame(3, User::query()->count());
    }
}
