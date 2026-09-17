<?php

namespace Tests\Feature;

use App\Exports\PostsExport;
use App\Exports\UsersExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 导出跟随筛选回归：导出内容与列表页当前筛选条件同源，不再导出全量
 */
class ExportFilterTest extends TestCase
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

    // ---- 用户导出 ----

    public function test_user_export_without_filter_exports_all(): void
    {
        $export = new UsersExport(null);

        $this->assertSame(14, $export->query()->count());
    }

    public function test_user_export_follows_keyword(): void
    {
        $export = new UsersExport('管理员');

        $this->assertSame(1, $export->query()->count());
        $this->assertSame('管理员', $export->query()->first()->name);
    }

    public function test_user_export_route_accepts_search_param(): void
    {
        $this->actingAs($this->admin())
            ->get(route('users.export', ['search' => '管理员']))
            ->assertOk()
            ->assertHeaderContains('content-type', 'spreadsheetml');
    }

    // ---- 文章导出 ----

    public function test_post_export_follows_status_filter(): void
    {
        $published = new PostsExport(null, 'published');
        $draft = new PostsExport(null, 'draft');

        $this->assertSame(13, $published->query()->count());
        $this->assertSame(7, $draft->query()->count());
    }

    public function test_post_export_follows_keyword(): void
    {
        $export = new PostsExport('软删除', null);

        $this->assertSame(1, $export->query()->count());
    }

    public function test_post_export_route_accepts_filter_params(): void
    {
        $this->actingAs($this->admin())
            ->get(route('posts.export', ['status' => 'published']))
            ->assertOk()
            ->assertHeaderContains('content-type', 'spreadsheetml');
    }
}
