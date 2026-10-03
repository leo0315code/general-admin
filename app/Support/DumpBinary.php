<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * 数据库导出二进制（mysqldump）定位
 *
 * 为什么不写死路径：仓库里的配置一旦写死某台机器的绝对路径（例如
 * /Applications/ServBay/package/mysql/current/bin），换开发机、上测试服、
 * 迁云服务器就都得改源码，这正是配置本该避免的事。
 *
 * 策略是**运行时自动探测**，按优先级：
 *   1. env DB_DUMP_BINARY_PATH    —— 显式指定时优先（运维人工接管）
 *   2. PATH 环境变量里的目录       —— 绝大多数标准安装都能命中
 *   3. 常见安装目录候选表          —— 覆盖 ServBay / Homebrew / MAMP / XAMPP / 宝塔 / Docker 官方镜像等
 *   4. 都找不到返回 null           —— 由调用方给出清晰报错，而不是静默产出空备份
 *
 * 实现要点：全程只用 is_executable() 做文件探测，**不调用 shell_exec / exec**，
 * 因此在 disable_functions 禁了命令函数的环境里同样可用，也不需要区分系统 shell 差异。
 */
final class DumpBinary
{
    /** MySQL 导出程序名（Windows 需带 .exe 后缀） */
    public const MYSQLDUMP = 'mysqldump';

    /**
     * 常见安装目录候选（不含 PATH）。
     *
     * 覆盖面：包管理器、集成环境、面板默认路径、Docker 官方镜像 PATH 之外的位置。
     * 新增只需往这里追加目录，无需改任何业务逻辑。
     *
     * @var list<string>
     */
    public const CANDIDATE_DIRS = [
        // macOS 集成环境与包管理器
        '/Applications/ServBay/package/mysql/current/bin',
        '/Applications/MAMP/Library/bin',
        '/Applications/XAMPP/xamppfiles/bin',
        '/opt/homebrew/bin',
        '/opt/local/lib/mysql8/bin',
        '/opt/local/bin',
        // Linux 常见位置
        '/usr/bin',
        '/usr/local/bin',
        '/usr/local/mysql/bin',
        '/usr/sbin',
        // 面板 / 容器常见位置
        '/www/server/mysql/bin',
        '/usr/local/mariadb/bin',
        // Windows 集成环境
        'C:\\xampp\\mysql\\bin',
        'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin',
    ];

    /** 单次进程内的探测结果缓存（避免同一请求重复 stat） */
    private static ?string $resolved = null;

    private static bool $resolvedDone = false;

    /**
     * 返回 mysqldump 所在**目录**（不含可执行文件名）。
     * 找不到返回 null。
     */
    public static function directory(): ?string
    {
        if (self::$resolvedDone) {
            return self::$resolved;
        }

        self::$resolvedDone = true;

        return self::$resolved = self::detect();
    }

    /**
     * 把探测结果注入数据库连接配置，供 spatie/db-dumper 使用。
     *
     * 仅在存在 MySQL 连接且尚未显式配置 dump_binary_path 时写入，
     * 保证运维在 .env 里的显式配置永远优先。
     */
    public static function inject(?string $dir = null): void
    {
        $dir ??= self::directory();

        if ($dir === null) {
            return;
        }

        foreach (['mysql', 'mariadb'] as $connection) {
            $key = "database.connections.{$connection}.dump";

            if (Config::has("database.connections.{$connection}") === false) {
                continue;
            }

            $existing = Config::get($key, []);

            // 已显式指定（来自 env）则不覆盖
            if (is_array($existing) && ! empty($existing['dump_binary_path'])) {
                continue;
            }

            Config::set($key, array_merge(is_array($existing) ? $existing : [], [
                'dump_binary_path' => $dir,
                // 单事务导出：MyISAM 之外的表都能拿到一致性快照，且不锁表
                'use_single_transaction' => true,
            ]));
        }
    }

    /**
     * 清除进程内缓存（仅测试用：静态缓存会让同一进程内的多次探测直接命中旧结果）。
     *
     * @internal
     */
    public static function forget(): void
    {
        self::$resolved = null;
        self::$resolvedDone = false;
    }

    private static function detect(): ?string
    {
        // 1) 显式指定优先
        $explicit = env('DB_DUMP_BINARY_PATH');

        if (is_string($explicit) && $explicit !== '' && self::hasDump($explicit)) {
            return rtrim($explicit, '/\\');
        }

        // 2) PATH 中的目录
        $path = getenv('PATH') ?: '';
        $separator = DIRECTORY_SEPARATOR === '\\' ? ';' : ':';

        foreach (explode($separator, $path) as $dir) {
            $dir = trim($dir);

            if ($dir !== '' && self::hasDump($dir)) {
                return rtrim($dir, '/\\');
            }
        }

        // 3) 候选目录表
        foreach (self::CANDIDATE_DIRS as $dir) {
            if (self::hasDump($dir)) {
                return rtrim($dir, '/\\');
            }
        }

        return null;
    }

    /** 目录下是否存在可执行/可读的 mysqldump */
    private static function hasDump(string $dir): bool
    {
        if (! is_dir($dir)) {
            return false;
        }

        $normalized = rtrim($dir, '/\\');

        foreach (self::binaryNames() as $name) {
            $file = $normalized.DIRECTORY_SEPARATOR.$name;

            if (is_file($file) && (is_executable($file) || is_readable($file))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> Windows 需要 .exe，两者都查以兼容不同系统 */
    private static function binaryNames(): array
    {
        return DIRECTORY_SEPARATOR === '\\'
            ? [self::MYSQLDUMP.'.exe', self::MYSQLDUMP]
            : [self::MYSQLDUMP];
    }
}
