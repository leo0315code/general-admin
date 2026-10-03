<?php

namespace Tests\Feature;

use App\Exceptions\UploadException;
use App\Models\Attachment;
use App\Models\User;
use App\Support\Uploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * 附件上传基座
 *
 * 重点验证安全基线：
 * 1. 扩展名 + 真实 MIME 双重白名单（任一不符即拒绝，且不留下文件）；
 * 2. 存储文件名随机化（与用户提供的文件名无关，防目录穿越/覆盖）；
 * 3. 文件不在 public 目录，下载必须鉴权；
 * 4. 普通用户只能碰自己的附件，删除会连带清理磁盘文件。
 */
class AttachmentTest extends TestCase
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

    public function test_admin_can_upload_and_file_lands_on_private_disk(): void
    {
        $admin = $this->admin();
        $file = UploadedFile::fake()->image('报表截图.png', 20, 20);

        $this->actingAs($admin)
            ->post(route('attachments.store'), ['file' => $file])
            ->assertRedirect();

        $attachment = Attachment::query()->firstOrFail();

        $this->assertSame('报表截图.png', $attachment->name, '原始文件名只用于展示与下载');
        $this->assertStringEndsWith('.png', $attachment->path);
        $this->assertStringNotContainsString('报表', $attachment->path, '存储名必须随机化，与上传名无关');
        $this->assertStringStartsWith('attachments/', $attachment->path);
        $this->assertSame($admin->id, $attachment->user_id);

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertNotNull($attachment->checksum);
    }

    /** 有上传按钮权限时，页面必须渲染上传组件的挂载点（否则点了没反应） */
    public function test_index_renders_uploader_mount_point(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('attachments.index'))
            ->assertOk()
            ->assertSee('data-component="attachment-uploader"', false);
    }

    /** 异步上传（前端 fetch）：成功返回 JSON，便于页面直接提示结果 */
    public function test_async_upload_returns_json_success(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('attachments.store'), [
                'file' => UploadedFile::fake()->image('异步上传.png', 10, 10),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('name', '异步上传.png');

        $this->assertSame(1, Attachment::query()->count());
    }

    /** 异步上传失败：返回 422 JSON 且带可读原因（前端直接 toast 出来） */
    public function test_async_upload_failure_returns_json_message(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('attachments.store'), ['file' => UploadedFile::fake()->create('木马.php', 5)])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message']);

        $this->assertSame(0, Attachment::query()->count());
    }

    /** 异步上传校验失败：沿用 Laravel 标准 422 errors 结构 */
    public function test_async_upload_validation_error_returns_422(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('attachments.store'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_rejects_extension_outside_whitelist(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('attachments.store'), ['file' => UploadedFile::fake()->create('shell.php', 5)])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Attachment::query()->count());
        $this->assertEmpty(Storage::disk('local')->allFiles(), '被拒的上传不应留下文件');
    }

    public function test_upload_rejects_mime_outside_whitelist_even_with_image_extension(): void
    {
        $admin = $this->admin();

        // 扩展名 .png 合法，但真实 MIME 是 PHP —— 双重校验的第二道必须拦住
        $this->actingAs($admin)
            ->post(route('attachments.store'), [
                'file' => UploadedFile::fake()->create('evil.png', 5, 'application/x-httpd-php'),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_upload_rejects_oversized_file(): void
    {
        $admin = $this->admin();
        config(['uploads.max_size' => 1]); // 1KB，方便造出超限样本

        // HTTP 层：表单校验先拦（返回字段错误）
        $this->actingAs($admin)
            ->post(route('attachments.store'), [
                'file' => UploadedFile::fake()->create('big.txt', 500), // 500KB
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());

        // 服务层：绕过表单校验直接调用同样必须拒绝（不依赖 php.ini / 表单规则）
        $this->expectException(UploadException::class);
        Uploader::store(UploadedFile::fake()->create('big.txt', 500), $admin);
    }

    public function test_upload_requires_button_permission(): void
    {
        // 自建用户：只给菜单级权限，不给 attachments.upload
        $viewer = User::factory()->create();
        $viewer->syncPermissions(['attachments.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($viewer)
            ->post(route('attachments.store'), ['file' => UploadedFile::fake()->image('a.png', 5, 5)])
            ->assertForbidden();

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_download_streams_file_with_attachment_disposition(): void
    {
        $admin = $this->admin();
        $attachment = Uploader::store(UploadedFile::fake()->image('凭证.png', 5, 5), $admin);

        $response = $this->actingAs($admin)->get(route('attachments.download', $attachment));

        $response->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
            '下载必须带 attachment，避免浏览器直接渲染上传内容'
        );
    }

    public function test_user_cannot_download_or_delete_others_attachment(): void
    {
        $owner = User::factory()->create();
        $owner->syncPermissions(['attachments.manage', 'attachments.upload', 'attachments.destroy']);

        $stranger = User::factory()->create();
        $stranger->syncPermissions(['attachments.manage', 'attachments.upload', 'attachments.destroy']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $attachment = Uploader::store(UploadedFile::fake()->image('私密.png', 5, 5), $owner);

        $this->actingAs($stranger)->get(route('attachments.download', $attachment))->assertForbidden();
        $this->actingAs($stranger)->delete(route('attachments.destroy', $attachment))->assertForbidden();

        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_owner_can_delete_attachment_and_file_is_removed(): void
    {
        $user = User::factory()->create();
        $user->syncPermissions(['attachments.manage', 'attachments.upload', 'attachments.destroy']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $attachment = Uploader::store(UploadedFile::fake()->image('临时.png', 5, 5), $user);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($user)
            ->delete(route('attachments.destroy', $attachment))
            ->assertRedirect()
            ->assertSessionHas('success');

        Storage::disk('local')->assertMissing($attachment->path);
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    }

    public function test_index_shows_only_own_attachments_for_non_admin(): void
    {
        $user = User::factory()->create();
        $user->syncPermissions(['attachments.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mine = Uploader::store(UploadedFile::fake()->image('我的.png', 5, 5), $user);
        $theirs = Uploader::store(UploadedFile::fake()->image('他人的.png', 5, 5), $this->admin());

        $html = (string) $this->actingAs($user)->get(route('attachments.index'))->assertOk()->getContent();

        $this->assertStringContainsString('我的.png', $html);
        $this->assertStringNotContainsString('他人的.png', $html);
        $this->assertNotNull($mine->id);
        $this->assertNotNull($theirs->id);
    }

    public function test_uploader_reports_readable_reason_on_failure(): void
    {
        $this->expectException(UploadException::class);

        try {
            Uploader::store(UploadedFile::fake()->create('x.exe', 5), $this->admin());
        } catch (UploadException $e) {
            $this->assertStringContainsString('不支持的文件类型', $e->userMessage());

            throw $e;
        }
    }

    public function test_attachment_page_requires_menu_permission(): void
    {
        $guest = User::factory()->create(); // 无任何权限

        $this->actingAs($guest)->get(route('attachments.index'))->assertForbidden();
    }
}
