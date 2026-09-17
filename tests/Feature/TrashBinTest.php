<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回收站回归：软删除 → 回收站列表 → 还原 → 彻底删除 全链路
 */
class TrashBinTest extends TestCase
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

    // ---- 用户回收站 ----

    public function test_deleted_user_appears_in_trash_then_restores(): void
    {
        $user = User::query()->where('email', 'user2@example.com')->firstOrFail();

        $this->actingAs($this->admin())->delete(route('users.destroy', $user))->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // 回收站列表可见
        $this->actingAs($this->admin())
            ->get(route('users.trash'))
            ->assertOk()
            ->assertSee('测试用户2');

        // 还原后回到正常列表、可正常登录
        $this->actingAs($this->admin())
            ->patch(route('users.restore', $user->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);

        $this->post('/console/login', ['login' => 'user2@example.com', 'password' => 'password'])
            ->assertRedirect('/console/dashboard');
    }

    public function test_force_delete_removes_user_permanently(): void
    {
        $user = User::query()->where('email', 'user3@example.com')->firstOrFail();
        $this->actingAs($this->admin())->delete(route('users.destroy', $user));

        $this->actingAs($this->admin())
            ->delete(route('users.force-destroy', $user->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_trash_index_shows_trashed_count_badge(): void
    {
        $user = User::query()->where('email', 'user4@example.com')->firstOrFail();
        $this->actingAs($this->admin())->delete(route('users.destroy', $user));

        $this->actingAs($this->admin())
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('回收站')
            ->assertSee('1');
    }

    public function test_guest_cannot_access_trash(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('users.trash'))->assertRedirect(route('login'));
    }

    // ---- 文章回收站 ----

    public function test_deleted_post_appears_in_trash_then_restores(): void
    {
        $post = Post::query()->where('title', '文章发布流程介绍')->firstOrFail();

        $this->actingAs($this->admin())->delete(route('posts.destroy', $post))->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);

        $this->actingAs($this->admin())
            ->get(route('posts.trash'))
            ->assertOk()
            ->assertSee('文章发布流程介绍');

        $this->actingAs($this->admin())
            ->patch(route('posts.restore', $post->id))
            ->assertRedirect();

        $this->assertNotSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_force_delete_removes_post_permanently(): void
    {
        $post = Post::query()->first();
        $this->actingAs($this->admin())->delete(route('posts.destroy', $post));

        $this->actingAs($this->admin())
            ->delete(route('posts.force-destroy', $post->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
