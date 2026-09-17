<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 文章管理（示例 CRUD）功能测试：列表、创建、编辑、删除、状态切换
 */
class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_admin_can_view_post_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('文章管理');
    }

    public function test_post_list_supports_keyword_search(): void
    {
        $post = Post::query()->first();

        $this->actingAs($this->admin)
            ->get(route('posts.index', ['search' => $post->title]))
            ->assertOk()
            ->assertSee($post->title);
    }

    public function test_admin_can_create_published_post(): void
    {
        $this->actingAs($this->admin)
            ->post(route('posts.store'), [
                'title' => '测试文章标题',
                'content' => '测试文章内容',
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => null,
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseHas('posts', [
            'title' => '测试文章标题',
            'status' => Post::STATUS_PUBLISHED,
        ]);

        // 发布且未指定时间 → 自动取当前时间
        $created = Post::query()->where('title', '测试文章标题')->firstOrFail();
        $this->assertNotNull($created->published_at);
    }

    public function test_admin_can_create_draft_post_without_published_at(): void
    {
        $this->actingAs($this->admin)
            ->post(route('posts.store'), [
                'title' => '草稿文章',
                'content' => '草稿内容',
                'status' => Post::STATUS_DRAFT,
                'published_at' => null,
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseHas('posts', ['title' => '草稿文章', 'status' => Post::STATUS_DRAFT]);
        $this->assertNull(Post::query()->where('title', '草稿文章')->firstOrFail()->published_at);
    }

    public function test_title_is_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('posts.store'), [
                'title' => '',
                'content' => '内容',
                'status' => Post::STATUS_DRAFT,
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_admin_can_update_post(): void
    {
        $post = Post::factory()->draft()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->patch(route('posts.update', $post), [
                'title' => '更新后的标题',
                'content' => '更新后的内容',
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => null,
            ])
            ->assertRedirect(route('posts.edit', $post));

        $post->refresh();
        $this->assertSame('更新后的标题', $post->title);
        $this->assertSame(Post::STATUS_PUBLISHED, $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_admin_can_toggle_post_status(): void
    {
        $post = Post::factory()->draft()->create(['user_id' => $this->admin->id]);

        // 草稿 → 发布
        $this->actingAs($this->admin)
            ->patch(route('posts.toggle-status', $post))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(Post::STATUS_PUBLISHED, $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);

        // 发布 → 草稿
        $this->actingAs($this->admin)
            ->patch(route('posts.toggle-status', $post->fresh()))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(Post::STATUS_DRAFT, $post->fresh()->status);
        $this->assertNull($post->fresh()->published_at);
    }

    public function test_admin_can_soft_delete_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'));

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_editor_with_post_manage_can_manage_posts(): void
    {
        $editor = User::query()->where('email', 'editor@example.com')->firstOrFail();

        $this->actingAs($editor)->get(route('posts.index'))->assertOk();
    }

    public function test_guest_cannot_access_post_management(): void
    {
        $this->get(route('posts.index'))->assertRedirect(route('login'));
    }
}
