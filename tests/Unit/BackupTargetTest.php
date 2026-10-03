<?php

namespace Tests\Unit;

use App\Support\BackupTarget;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 备份目标磁盘决策：本地恒在，异地（阿里云 OSS）按环境变量启用。
 */
class BackupTargetTest extends TestCase
{
    protected function tearDown(): void
    {
        // 还原环境变量，避免污染其他用例
        foreach (['OSS_ACCESS_KEY', 'OSS_SECRET_KEY', 'OSS_ENDPOINT', 'OSS_BUCKET'] as $key) {
            Env::getRepository()->clear($key);
        }

        Storage::forgetDisk(BackupTarget::OFFSITE);

        parent::tearDown();
    }

    private function fillCredentials(): void
    {
        foreach (['OSS_ACCESS_KEY', 'OSS_SECRET_KEY', 'OSS_ENDPOINT', 'OSS_BUCKET'] as $key) {
            Env::getRepository()->set($key, 'fake-'.$key);
        }
    }

    public function test_default_targets_are_local_only(): void
    {
        $this->assertSame(['backups'], BackupTarget::disks());
        $this->assertNull(BackupTarget::offsite());
        $this->assertFalse(BackupTarget::hasOffsite());
    }

    public function test_offsite_enabled_when_all_four_credentials_present(): void
    {
        $this->fillCredentials();

        $this->assertTrue(BackupTarget::configured());
        $this->assertSame(BackupTarget::OFFSITE, BackupTarget::offsite());
        $this->assertSame(['backups', BackupTarget::OFFSITE], BackupTarget::disks());
        $this->assertTrue(BackupTarget::hasOffsite());
    }

    /**
     * 凭证缺一项就不启用：与其让 OSS SDK 抛难懂异常，不如安静退回本地备份。
     */
    public function test_offsite_disabled_when_any_credential_missing(): void
    {
        Env::getRepository()->set('OSS_ACCESS_KEY', 'k');
        Env::getRepository()->set('OSS_SECRET_KEY', 's');
        Env::getRepository()->set('OSS_ENDPOINT', 'https://oss-cn-hangzhou.aliyuncs.com');
        // 缺 OSS_BUCKET

        $this->assertFalse(BackupTarget::configured());
        $this->assertSame(['backups'], BackupTarget::disks());
    }

    public function test_oss_driver_is_available_and_disk_resolves(): void
    {
        $this->assertTrue(BackupTarget::driverAvailable(), 'iidestiny/flysystem-oss 未安装');

        Config::set('filesystems.disks.'.BackupTarget::OFFSITE, [
            'driver' => 'oss',
            'access_key' => 'fake-key',
            'secret_key' => 'fake-secret',
            'endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
            'bucket' => 'fake-bucket',
            'prefix' => 'backups',
        ]);
        Storage::forgetDisk(BackupTarget::OFFSITE);

        // 只构造客户端对象，不发起网络请求：无凭证也能解析出磁盘实例
        $this->assertInstanceOf(Filesystem::class, Storage::disk(BackupTarget::OFFSITE));
    }
}
