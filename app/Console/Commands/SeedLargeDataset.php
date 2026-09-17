<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * 大数据量造数命令（UI 现代化重构 · T02 / PERF-1）
 *
 * 生成大量用户与文章，用于列表页首屏/翻页性能、索引 EXPLAIN 与导出内存峰值验证。
 *
 * 用法：
 *   php artisan app:seed-large-dataset --count=100000 --with-posts=50000 [--truncate]
 *
 * 防护与约束：
 * - APP_ENV=production 下必须加 --force 才执行；
 * - APP_ENV=testing 下直接拒绝，避免污染测试数据；
 * - 用户名称使用「压测用户{n}」，与基座 UserSeeder 的「测试用户1..12」互不冲突；
 *   邮箱沿用设计约定的 test+{n}@example.com（与基座 user{n}@example.com 命名空间隔离），
 *   起始编号自动接续既有 test+ 用户的最大后缀，重复执行（不带 --truncate）不会撞唯一键；
 * - --truncate 会清空 users / posts（含 admin/editor 与全部业务数据），
 *   随后需执行 php artisan db:seed 恢复基座数据。
 */
class SeedLargeDataset extends Command
{
    /** @var string */
    protected $signature = 'app:seed-large-dataset
        {--count=100000 : 生成用户数量}
        {--with-posts=50000 : 生成文章数量（0 表示不生成）}
        {--truncate : 先清空 users / posts 表再插入（会删除 admin/editor 与全部数据，完成后需 php artisan db:seed 恢复）}
        {--force : 生产环境强制执行}';

    /** @var string */
    protected $description = '生成大数据量测试数据（用户 + 文章），用于列表性能验证';

    /** 压测用户邮箱前缀（设计约定 test+{n}@example.com，与基座 user{n}@example.com 隔离） */
    private const EMAIL_PREFIX = 'test+';

    /** 压测用户名称前缀（与基座「测试用户」命名空间隔离，避免撞唯一键） */
    private const NAME_PREFIX = '压测用户';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('生产环境禁止执行造数命令，请加 --force 明确确认。');

            return self::FAILURE;
        }

        if (app()->environment('testing')) {
            $this->error('测试环境禁止执行造数命令，避免污染测试数据。');

            return self::FAILURE;
        }

        $count = max(1, (int) $this->option('count'));
        $withPosts = max(0, (int) $this->option('with-posts'));

        if ($this->option('truncate')) {
            $this->warn('警告：--truncate 将清空 users / posts（含 admin/editor 与全部业务数据），完成后请执行 php artisan db:seed 恢复基座数据。');
            $this->truncateTables();
        }

        $this->info("开始生成 {$count} 个用户…");
        $this->bulkInsertUsers($count);

        if ($withPosts > 0) {
            $this->info("开始生成 {$withPosts} 篇文章…");
            $this->bulkInsertPosts($withPosts);
        }

        $this->info('造数完成。');

        return self::SUCCESS;
    }

    /** 清空 users / posts（MySQL 下关闭外键检查，避免 posts.user_id 外键阻止 truncate）。 */
    protected function truncateTables(): void
    {
        $driver = DB::connection()->getDriverName();
        $disableFk = $driver === 'mysql';

        if ($disableFk) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::table('posts')->truncate();
            DB::table('users')->truncate();
        } finally {
            if ($disableFk) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $this->info('已清空 users / posts 表。');
    }

    /** 批量插入用户（5000/批，复用同一个 bcrypt hash），并给部分用户挂 editor 角色。 */
    protected function bulkInsertUsers(int $count): void
    {
        $hash = Hash::make('password123');
        $now = now();
        $chunk = 5000;
        // 起始编号接续既有 loadtest 用户的最大后缀，重复执行不撞唯一键
        $start = $this->nextLoadTestIndex();

        for ($i = 0; $i < $count; $i += $chunk) {
            $rows = [];
            $end = min($i + $chunk, $count);

            for ($j = $i; $j < $end; $j++) {
                $n = $start + $j;

                $rows[] = [
                    'name' => self::NAME_PREFIX.$n,
                    'email' => self::EMAIL_PREFIX.$n.'@example.com',
                    'email_verified_at' => $now,
                    'password' => $hash,
                    'status' => 1,
                    'must_change_password' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($rows);
            $this->info("  已插入 {$end} / {$count} 个用户");
        }

        // 每 1000 行挑 1 行挂 editor 角色（验证带角色列表的 N+1 场景）
        $editorRole = Role::query()->where('name', User::ROLE_EDITOR)->first();

        if ($editorRole) {
            for ($i = 0; $i < $count; $i += 1000) {
                $userId = DB::table('users')
                    ->where('email', self::EMAIL_PREFIX.($start + $i).'@example.com')
                    ->value('id');

                if ($userId) {
                    DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $editorRole->id,
                        'model_type' => User::class,
                        'model_id' => $userId,
                    ]);
                }
            }
        }
    }

    /** 计算下一个可用的 test+ 用户后缀（max + 1）。 */
    protected function nextLoadTestIndex(): int
    {
        $suffixes = DB::table('users')
            ->where('email', 'like', self::EMAIL_PREFIX.'%@example.com')
            ->pluck('email')
            ->map(function (string $email): int {
                $between = Str::between($email, self::EMAIL_PREFIX, '@example.com');

                return ctype_digit($between) ? (int) $between : -1;
            });

        if ($suffixes->isEmpty()) {
            return 0;
        }

        return max(0, (int) $suffixes->max() + 1);
    }

    /** 批量插入文章（5000/批，随机作者，约 1/3 已发布）。 */
    protected function bulkInsertPosts(int $count): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            $this->warn('没有用户，跳过文章生成。');

            return;
        }

        $chunk = 5000;
        $now = now();

        for ($i = 0; $i < $count; $i += $chunk) {
            $rows = [];
            $end = min($i + $chunk, $count);

            for ($j = $i; $j < $end; $j++) {
                $status = $j % 3 === 0 ? 'published' : 'draft';

                $rows[] = [
                    'user_id' => $userIds[$j % count($userIds)],
                    'title' => "批量文章 {$j}",
                    'content' => "这是批量生成的文章内容 {$j}。",
                    'status' => $status,
                    'published_at' => $status === 'published' ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('posts')->insert($rows);
            $this->info("  已插入 {$end} / {$count} 篇文章");
        }
    }
}
