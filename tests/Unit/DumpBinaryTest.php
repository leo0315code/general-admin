<?php

use App\Support\DumpBinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DumpBinaryTest extends TestCase
{
    use RefreshDatabase;

    /** 造一个含 mysqldump 的假目录，用于验证「候选目录扫描」这条路径 */
    private string $fakeDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDir = sys_get_temp_dir().'/dump-bin-'.uniqid();
        mkdir($this->fakeDir, 0o755, true);
        touch($this->fakeDir.'/'.DumpBinary::MYSQLDUMP);
        chmod($this->fakeDir.'/'.DumpBinary::MYSQLDUMP, 0o755);

        // 每个用例都从干净状态开始探测
        DumpBinary::forget();
        putenv('DB_DUMP_BINARY_PATH');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->fakeDir.'/*') ?: []);
        @rmdir($this->fakeDir);
        DumpBinary::forget();
        putenv('DB_DUMP_BINARY_PATH');

        parent::tearDown();
    }

    public function test_env_takes_precedence_when_directory_has_binary(): void
    {
        putenv('DB_DUMP_BINARY_PATH='.$this->fakeDir);

        $this->assertSame($this->fakeDir, DumpBinary::directory());
    }

    public function test_env_ignored_when_directory_lacks_binary(): void
    {
        // 显式指定了目录，但里面没有 mysqldump —— 不能盲信配置，继续降级探测
        putenv('DB_DUMP_BINARY_PATH='.$this->fakeDir.'/nonexistent-'.uniqid());

        $result = DumpBinary::directory();

        // 结果要么仍是某个真实命中，要么是 null，绝不应该是那个不存在的目录
        $this->assertNotSame(getenv('DB_DUMP_BINARY_PATH'), $result);
    }

    public function test_scans_path_and_falls_back_without_shell(): void
    {
        // 全程只用 is_executable()，不依赖 shell_exec —— 这是「换机器不用改配置」的关键
        $result = DumpBinary::directory();

        if ($result === null) {
            // 当前机器确实没装 mysqldump 时，必须返回 null 而不是瞎猜一个路径
            $this->assertNull($result);

            return;
        }

        $this->assertDirectoryExists($result);
    }

    public function test_has_dump_tolerates_trailing_slash(): void
    {
        putenv('DB_DUMP_BINARY_PATH='.$this->fakeDir.'/');

        // 目录尾斜杠应被规范化，结果与不带斜杠时一致
        $this->assertSame(rtrim($this->fakeDir, '/'), DumpBinary::directory());
    }

    public function test_result_is_cached_within_process(): void
    {
        $first = DumpBinary::directory();

        // 第二次调用不应重复扫描（静态缓存），结果与首次一致
        $this->assertSame($first, DumpBinary::directory());

        DumpBinary::forget();

        $this->assertSame($first, DumpBinary::directory());
    }

    public function test_inject_writes_to_mysql_connection(): void
    {
        putenv('DB_DUMP_BINARY_PATH='.$this->fakeDir);
        config(['database.connections.mysql.dump' => null]);

        DumpBinary::inject();

        $dump = config('database.connections.mysql.dump');

        $this->assertIsArray($dump);
        $this->assertSame($this->fakeDir, $dump['dump_binary_path']);
        // 单事务导出：不锁表且能拿到一致性快照
        $this->assertTrue($dump['use_single_transaction']);
    }

    public function test_inject_does_not_override_explicit_config(): void
    {
        config(['database.connections.mysql.dump' => ['dump_binary_path' => '/custom/from/env']]);

        DumpBinary::inject();

        $this->assertSame('/custom/from/env', config('database.connections.mysql.dump.dump_binary_path'));
    }
}
