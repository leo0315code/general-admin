<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Post;
use App\Models\User;
use App\Support\Uploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * 附件接入业务：文章封面
 *
 * 关注五件事：
 * 1. 封面上传走附件基座（落 attachments 表），仅放行图片，需 posts.create/update；
 * 2. 非 admin 只能用「自己的附件」当封面（归属校验，防越权引用他人文件）；
 * 3. 预览只对 本人 / admin / 被文章引用的附件 放行（列表展示需要）；
 * 4. attachments:prune 绝不清理被文章引用的封面（即使超过保留期）；
 * 5. 搜索通配符转义：输入 % 不再命中全表。
 */
class PostCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    /** 有 post.manage 菜单权限、但无 posts.create/update 按钮权限 */
    private function editor(): User
    {
        return User::factory()->create()->assignRole(User::ROLE_EDITOR);
    }

    private function uploadCover(User $user): Attachment
    {
        return Uploader::store(UploadedFile::fake()->image('cover.png', 200, 120), $user);
    }

    public function test_cover_upload_requires_post_manage_permission(): void
    {
        // 无任何角色的用户：连 post.manage 菜单权限都没有 → 路由组直接 403
        $this->actingAs(User::factory()->create())
            ->post(route('posts.cover-upload'), ['file' => UploadedFile::fake()->image('c.png')])
            ->assertForbidden();
    }

    public function test_cover_upload_requires_create_or_update_permission(): void
    {
        // 有 post.manage（过路由组）但无 posts.create/update 的用户 → 方法内 403
        $viewer = User::factory()->create()->givePermissionTo('post.manage');

        $this->actingAs($viewer)
            ->post(route('posts.cover-upload'), ['file' => UploadedFile::fake()->image('c.png')])
            ->assertForbidden();
    }

    public function test_cover_upload_rejects_non_image(): void
    {
        $this->actingAs($this->admin())
            ->post(route('posts.cover-upload'), ['file' => UploadedFile::fake()->create('doc.txt', 10)], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(422);
    }

    public function test_cover_upload_returns_attachment(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)
            ->post(route('posts.cover-upload'), ['file' => UploadedFile::fake()->image('cover.png')])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attachments', ['id' => $res->json('id'), 'user_id' => $admin->id]);

        $payload = $res->json();
        $this->assertStringContainsString(route('posts.cover-preview', $payload['id']), $payload['preview_url']);
    }

    public function test_store_rejects_others_attachment_as_cover(): void
    {
        $admin = $this->admin();
        $other = $this->uploadCover($admin); // 别人（admin）上传的附件

        $this->actingAs(User::factory()->create()->assignRole(User::ROLE_EDITOR))
            ->post(route('posts.store'), [
                'title' => '测试',
                'content' => '正文',
                'status' => Post::STATUS_DRAFT,
                'cover_attachment_id' => $other->id,
            ])
            ->assertSessionHasErrors('cover_attachment_id');
    }

    public function test_store_accepts_own_attachment_as_cover(): void
    {
        $editor = User::factory()->create()->assignRole(User::ROLE_EDITOR);
        $cover = $this->uploadCover($editor);

        $this->actingAs($editor)
            ->post(route('posts.store'), [
                'title' => '带封面',
                'content' => '正文',
                'status' => Post::STATUS_DRAFT,
                'cover_attachment_id' => $cover->id,
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseHas('posts', ['title' => '带封面', 'cover_attachment_id' => $cover->id]);
    }

    public function test_preview_allows_owner_and_admin(): void
    {
        $editor = User::factory()->create()->assignRole(User::ROLE_EDITOR);
        $cover = $this->uploadCover($editor);

        // 本人（editor 有 post.manage 菜单权限）
        $this->actingAs($editor)->get(route('posts.cover-preview', $cover))->assertOk();

        // admin
        $this->actingAs($this->admin())->get(route('posts.cover-preview', $cover))->assertOk();
    }

    public function test_preview_requires_reference_for_strangers(): void
    {
        $owner = User::factory()->create()->assignRole(User::ROLE_EDITOR);
        $cover = $this->uploadCover($owner);
        $stranger = User::factory()->create()->assignRole(User::ROLE_EDITOR);

        // 非本人、非 admin、未被引用 → 403
        $this->actingAs($stranger)->get(route('posts.cover-preview', $cover))->assertForbidden();

        // 一旦被文章引用为封面（列表页展示需要）→ 放行
        Post::query()->create([
            'user_id' => $owner->id,
            'title' => '引用该封面',
            'content' => '正文',
            'status' => Post::STATUS_DRAFT,
            'cover_attachment_id' => $cover->id,
        ]);

        $this->actingAs($stranger)->get(route('posts.cover-preview', $cover))->assertOk();
    }

    public function test_prune_keeps_referenced_covers(): void
    {
        $admin = $this->admin();
        $cover = $this->uploadCover($admin);
        $unused = $this->uploadCover($admin);

        Post::query()->create([
            'user_id' => $admin->id,
            'title' => '封面被引用',
            'content' => '正文',
            'status' => Post::STATUS_DRAFT,
            'cover_attachment_id' => $cover->id,
        ]);

        // 两个附件都造得比保留期早（created_at 不在 fillable，须用 query builder 改）
        Attachment::query()->whereIn('id', [$cover->id, $unused->id])->update(['created_at' => now()->subDays(400)]);

        $this->artisan('attachments:prune', ['--days' => 90])->assertSuccessful();

        // 被引用的封面保留，没被引用的过期附件清理
        $this->assertDatabaseHas('attachments', ['id' => $cover->id]);
        $this->assertDatabaseMissing('attachments', ['id' => $unused->id]);
    }

    public function test_search_escapes_wildcards(): void
    {
        $admin = $this->admin();

        // 造两条标题带 % 的文章 + 一条普通文章
        Post::query()->create(['user_id' => $admin->id, 'title' => '折扣 100% 商品', 'content' => 'a', 'status' => Post::STATUS_DRAFT]);
        Post::query()->create(['user_id' => $admin->id, 'title' => '折扣 100% 服务', 'content' => 'b', 'status' => Post::STATUS_DRAFT]);
        Post::query()->create(['user_id' => $admin->id, 'title' => '普通标题', 'content' => 'c', 'status' => Post::STATUS_DRAFT]);

        // 输入单个 % 只应命中标题含 % 的两条，而不是全表
        $html = (string) $this->actingAs($admin)
            ->get(route('posts.index', ['search' => '%']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('折扣 100% 商品', $html);
        $this->assertStringContainsString('折扣 100% 服务', $html);
        $this->assertStringNotContainsString('普通标题', $html);
    }
}
