<?php

namespace Tests\Feature;

use App\Exceptions\UploadException;
use App\Models\Attachment;
use App\Models\User;
use App\Support\Uploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 附件运维：用户配额 + 过期/孤儿清理
 *
 * 基座原本「只增不减」：没有业务模块引用，也没有清理手段，磁盘会被慢慢写满。
 * 这里验证两块：上传前的配额拦截，以及 attachments:prune 的清理行为。
 */
class AttachmentMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    /**
     * 建一条附件记录（created_at 不在 fillable 里，必须 forceFill 才能造历史数据）
     *
     * @param  int|null  $daysAgo  null 表示当前时间
     */
    private function makeAttachment(User $user, string $path, int $size = 1024, ?int $daysAgo = null): Attachment
    {
        $attachment = Attachment::query()->create([
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $path,
            'name' => basename($path),
            'extension' => 'txt',
            'mime' => 'text/plain',
            'size' => $size,
            'checksum' => null,
        ]);

        if ($daysAgo !== null) {
            // 直接用 query builder 改，绕开 fillable 限制
            Attachment::query()->whereKey($attachment->id)->update([
                'created_at' => now()->subDays($daysAgo),
            ]);
            $attachment->refresh();
        }

        Storage::disk('local')->put($path, 'x');

        return $attachment;
    }

    public function test_upload_rejected_when_user_quota_exceeded(): void
    {
        $admin = $this->admin();

        config(['uploads.user_quota' => 1]); // 1MB
        $this->makeAttachment($admin, 'attachments/2026/09/old.txt', 900 * 1024);

        try {
            Uploader::store(UploadedFile::fake()->create('new.txt', 500), $admin);

            $this->fail('超配额时应抛出 UploadException');
        } catch (UploadException $e) {
            $this->assertStringContainsString('附件空间已用完', $e->userMessage());
        }

        $this->assertSame(1, Attachment::query()->count(), '超限的文件不应落库');
    }

    public function test_upload_allowed_within_quota(): void
    {
        $admin = $this->admin();

        config(['uploads.user_quota' => 5]); // 5MB

        $attachment = Uploader::store(UploadedFile::fake()->create('ok.txt', 100), $admin);

        $this->assertNotNull($attachment->id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_prune_dry_run_deletes_nothing(): void
    {
        $admin = $this->admin();
        $old = $this->makeAttachment($admin, 'attachments/2026/01/old.txt', 1024, 200);

        $this->artisan('attachments:prune --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('attachments', ['id' => $old->id]);
        Storage::disk('local')->assertExists($old->path);
    }

    public function test_prune_deletes_expired_attachments_and_files(): void
    {
        $admin = $this->admin();

        $old = $this->makeAttachment($admin, 'attachments/2026/01/old.txt', 1024, 200);
        $fresh = $this->makeAttachment($admin, 'attachments/2026/09/new.txt');

        $this->artisan('attachments:prune')->assertSuccessful();

        $this->assertDatabaseMissing('attachments', ['id' => $old->id]);
        $this->assertNotNull($fresh->fresh(), '保留期内的附件不能被删');

        Storage::disk('local')->assertMissing($old->path);
        Storage::disk('local')->assertExists($fresh->path);
    }

    public function test_prune_removes_orphan_files_without_db_record(): void
    {
        Storage::disk('local')->put('attachments/2026/09/ghost.txt', 'x');

        $this->artisan('attachments:prune --orphans-only')->assertSuccessful();

        Storage::disk('local')->assertMissing('attachments/2026/09/ghost.txt');
    }

    public function test_prune_keeps_files_that_have_records(): void
    {
        $admin = $this->admin();
        $attachment = $this->makeAttachment($admin, 'attachments/2026/09/keep.txt');

        $this->artisan('attachments:prune --orphans-only')->assertSuccessful();

        $this->assertTrue(
            Storage::disk('local')->exists($attachment->path),
            '有记录的文件不能被当孤儿清掉'
        );
    }

    public function test_days_option_is_respected(): void
    {
        $admin = $this->admin();
        $week = $this->makeAttachment($admin, 'attachments/2026/09/week.txt', 10, 10);

        $this->artisan('attachments:prune --days=5')->assertSuccessful();

        $this->assertDatabaseMissing('attachments', ['id' => $week->id]);
    }
}
