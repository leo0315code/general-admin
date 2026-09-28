<?php

namespace Tests\Feature;

use App\Imports\UsersImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * 用户批量导入回归：单行失败不得中断整批、不得 500，
 * 姓名/邮箱查重须覆盖软删除记录，密码留空生成随机强密码。
 */
class UserImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs($this->admin());
    }

    /** 构建内存 xlsx 上传文件（首行为表头） */
    private function makeImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(
            array_merge([['姓名', '邮箱', '密码', '角色']], $rows),
            null,
            'A1'
        );

        $tmp = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);

        return new UploadedFile(
            $tmp,
            'users.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function admin(): User
    {
        return User::query()->where('email', 'admin@example.com')->firstOrFail();
    }

    private function postImport(array $rows)
    {
        return $this->post(route('users.import'), ['file' => $this->makeImportFile($rows)]);
    }

    public function test_import_creates_users_with_random_password_when_blank(): void
    {
        $response = $this->postImport([
            ['张三', 'zhangsan@example.com', '', 'editor'],
            ['李四', 'lisi@example.com', '', 'admin'],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', ['name' => '张三', 'email' => 'zhangsan@example.com']);
        $this->assertDatabaseHas('users', ['name' => '李四', 'email' => 'lisi@example.com']);

        // 密码留空 → 随机强密码，绝不等于固定弱密码 123456
        foreach (['zhangsan@example.com', 'lisi@example.com'] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertFalse(Hash::check('123456', $user->password));
        }

        $this->assertSame(2, UsersImport::$created);
        $this->assertSame([], UsersImport::$errors);
    }

    public function test_import_skips_duplicate_name_and_does_not_500(): void
    {
        // 数据库已有「leo0315」（种子数据），导入同名行不得撞唯一索引 500
        $response = $this->postImport([
            ['leo0315', 'dup-admin@example.com', '', 'editor'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'dup-admin@example.com']);
        $this->assertSame(0, UsersImport::$created);
        $this->assertCount(1, UsersImport::$errors);
        $this->assertStringContainsString('姓名 leo0315 已存在', UsersImport::$errors[0]);
    }

    public function test_import_skips_duplicate_soft_deleted_user(): void
    {
        $deleted = User::query()->where('email', 'user1@example.com')->firstOrFail();
        $deleted->delete();

        $response = $this->postImport([
            ['测试用户1', 'user1@example.com', '', 'editor'],
        ]);

        $response->assertRedirect();
        $this->assertSame(0, UsersImport::$created);
        $this->assertCount(1, UsersImport::$errors);
    }

    public function test_import_skips_unknown_role(): void
    {
        $response = $this->postImport([
            ['王五', 'wangwu@example.com', '', 'super-admin'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'wangwu@example.com']);
        $this->assertCount(1, UsersImport::$errors);
        $this->assertStringContainsString('角色「super-admin」不存在', UsersImport::$errors[0]);
    }

    public function test_import_skips_short_password(): void
    {
        $response = $this->postImport([
            ['赵六', 'zhaoliu@example.com', 'abc', 'editor'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'zhaoliu@example.com']);
        $this->assertCount(1, UsersImport::$errors);
        $this->assertStringContainsString('密码少于 8 位', UsersImport::$errors[0]);
    }

    public function test_import_skips_row_missing_required_fields(): void
    {
        $response = $this->postImport([
            ['', 'no-name@example.com', '', 'editor'],
            ['无邮箱', '', '', 'editor'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'no-name@example.com']);
        $this->assertCount(2, UsersImport::$errors);
    }

    public function test_import_continues_after_failed_rows(): void
    {
        // 混排：1 行重复 + 1 行正常 → 正常行仍应导入成功
        $response = $this->postImport([
            ['leo0315', 'dup@example.com', '', 'editor'],
            ['孙七', 'sunqi@example.com', '', 'editor'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'sunqi@example.com']);
        $this->assertSame(1, UsersImport::$created);
        $this->assertCount(1, UsersImport::$errors);
    }

    public function test_guest_cannot_import(): void
    {
        // setUp 里 actingAs(admin) 会带入本用例，先清除登录态
        $this->app['auth']->forgetGuards();

        $this->post(route('users.import'), ['file' => $this->makeImportFile([['x', 'x@example.com', '', 'editor']])])
            ->assertRedirect(route('login'));
    }

    public function test_import_rejects_file_larger_than_5mb(): void
    {
        // 构造一个超过 max:5120 的假文件（无需真实 xlsx 内容，校验先于解析）
        $before = User::query()->count();
        $big = UploadedFile::fake()->create('big.xlsx', 6000, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->post(route('users.import'), ['file' => $big])
            ->assertSessionHasErrors('file');

        $this->assertSame($before, User::query()->count());
    }

    public function test_failed_rows_can_be_downloaded_as_xlsx(): void
    {
        // 第一行合法；第二行邮箱与已存在的 admin 冲突 → 失败；第三行角色不存在 → 失败
        $response = $this->postImport([
            ['张三', 'zhangsan@example.com', '', 'editor'],
            ['重名邮箱', 'admin@example.com', '', 'editor'],
            ['王五', 'wangwu@example.com', '', 'no-such-role'],
        ]);

        $response->assertRedirect();
        $token = session('import_errors_token');
        $this->assertNotEmpty($token, '存在失败行时应给出下载 token');

        // 结构化失败行：两条，含姓名/邮箱/角色/原因
        $this->assertCount(2, UsersImport::$failedRows);
        $this->assertSame('admin@example.com', UsersImport::$failedRows[0]['email']);
        $this->assertStringContainsString('不存在', UsersImport::$failedRows[1]['reason']);

        $download = $this->get(route('users.import-errors', $token));
        $download->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $download->headers->get('content-disposition')
        );
    }

    public function test_import_errors_token_is_single_use_and_expires(): void
    {
        $this->postImport([['重名邮箱', 'admin@example.com', '', 'editor']]);

        $token = session('import_errors_token');
        $this->get(route('users.import-errors', $token))->assertOk();

        // 一次性消费：第二次访问应提示过期
        $this->get(route('users.import-errors', $token))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');
    }

    public function test_downloading_import_errors_requires_permission(): void
    {
        // editor 角色权限清单为「仪表盘 + 文章管理」，不含 users.import（见 RolePermissionSeeder）
        $editor = User::factory()->create(['must_change_password' => false]);
        $editor->syncRoles([User::ROLE_EDITOR]);

        $this->assertFalse($editor->can('users.import'));

        $this->actingAs($editor)
            ->get(route('users.import-errors', Str::random(40)))
            ->assertForbidden();
    }
}
