<?php

namespace App\Support;

use Iidestiny\Flysystem\Oss\OssAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;

/**
 * 备份目标磁盘（本地 + 异地）。
 *
 * 设计原则与 DumpBinary 一致：**不在代码里写死任何环境信息**，
 * 全部由环境变量驱动。异地目标配置不完整或驱动没装时自动降级为纯本地，
 * 绝不因为异地配置问题拖垮本地备份。
 *
 * 异地用阿里云 OSS（iidestiny/flysystem-oss）。该包不带 Laravel 的
 * ServiceProvider，需要自己把 oss 驱动注册进 Storage 门面，见 registerOssDriver()。
 */
class BackupTarget
{
    /** 本地备份磁盘（恒在，见 config/filesystems.php） */
    public const LOCAL = 'backups';

    /** 异地备份磁盘（OSS，按需启用） */
    public const OFFSITE = 'backup_offsite';

    /** 启用异地备份所需的环境变量 */
    private const REQUIRED_ENV = ['OSS_ACCESS_KEY', 'OSS_SECRET_KEY', 'OSS_ENDPOINT', 'OSS_BUCKET'];

    /**
     * 备份目标磁盘列表：本地恒在，异地仅在配置完整且驱动可用时追加。
     */
    public static function disks(): array
    {
        return array_values(array_filter([self::LOCAL, self::offsite()]));
    }

    /**
     * 异地磁盘名；不满足启用条件时返回 null（纯本地备份）。
     */
    public static function offsite(): ?string
    {
        if (! self::configured() || ! self::driverAvailable()) {
            return null;
        }

        return self::OFFSITE;
    }

    /**
     * 环境变量是否配置完整（四项缺一不可）。
     *
     * 刻意不做「部分配置就尝试」：凭证不全时 OSS 会抛出难懂的 SDK 异常，
     * 不如在一开始就判定为未启用、走纯本地备份。
     */
    public static function configured(): bool
    {
        foreach (self::REQUIRED_ENV as $key) {
            if (trim((string) env($key)) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * OSS 驱动是否已安装。
     *
     * 用 class_exists 而非直接 new：未安装时调用备份不应崩溃，
     * 而是安静地退回本地备份（这也是「异地可选」的体现）。
     */
    public static function driverAvailable(): bool
    {
        return class_exists(OssAdapter::class);
    }

    /**
     * 是否启用了异地备份（供 continue_on_failure 等开关判断）。
     */
    public static function hasOffsite(): bool
    {
        return self::offsite() !== null;
    }

    /**
     * 注册 oss 磁盘驱动。
     *
     * 目录前缀作为适配器构造参数传入（config 的 oss_prefix），
     * 不用 Laravel 的 'prefix' 磁盘配置：当前依赖组合下那条路径会引用
     * 不存在的 League\Flysystem\PathPrefixing\PathPrefixedAdapter 而报错。
     * 同理，传给 FilesystemAdapter 的 config 要剔除 oss_prefix，避免二次加前缀。
     */
    public static function registerOssDriver(): void
    {
        if (! self::driverAvailable()) {
            return;
        }

        Storage::extend('oss', function ($app, array $config) {
            $adapter = new OssAdapter(
                (string) ($config['access_key'] ?? ''),
                (string) ($config['secret_key'] ?? ''),
                (string) ($config['endpoint'] ?? ''),
                (string) ($config['bucket'] ?? ''),
                (bool) ($config['is_cname'] ?? false),
                (string) ($config['oss_prefix'] ?? ''),
            );

            $adapterConfig = $config;
            unset($adapterConfig['oss_prefix']);

            return new FilesystemAdapter(new Flysystem($adapter, $adapterConfig), $adapter, $adapterConfig);
        });
    }
}
