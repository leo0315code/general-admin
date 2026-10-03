<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 权限目录一致性（防止「幽灵权限」与拼写错误）
 *
 * 本项目「菜单即权限」：权限由 MenuPermissionSeeder 依据菜单树写入 permissions 表。
 * 若代码里写了一个菜单树中不存在的权限名（拼错、改名漏改、删菜单忘删代码），
 * Gate 会恒返回 false —— 表现为「按钮永远不显示 / 操作永远 403」，
 * 且在无 admin 的账号上才会暴露，极难排查。
 *
 * 本测试把「代码用到的权限名 ⊆ 权限表」固化为契约。
 */
class PermissionCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * 扫描目录中形如 Gate::authorize('x.y') / @can('x.y') 的权限名
     *
     * 只收「带点号」的自定义能力名：裸的 update/delete 属 Policy 能力（模型方法名）。
     *
     * @return list<string>
     */
    private function usedPermissions(string $dir, string $pattern): array
    {
        $names = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match_all($pattern, $contents, $matches) > 0) {
                foreach ($matches[1] as $name) {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function test_controller_permissions_are_declared_in_permission_table(): void
    {
        $used = $this->usedPermissions(
            app_path(),
            '/(?:Gate::authorize|Gate::check|Gate::allows|Gate::denies)\(\s*\'([a-z0-9]+\.[a-z0-9._-]+)\'/'
        );

        $this->assertNotEmpty($used, '未扫描到任何权限调用，正则可能已失效');

        $known = Permission::query()->pluck('name')->all();
        $unknown = array_values(array_diff($used, $known));

        $this->assertSame([], $unknown, '存在未在菜单树/权限表中登记的「幽灵权限」');
    }

    public function test_view_permissions_are_declared_in_permission_table(): void
    {
        $used = $this->usedPermissions(
            resource_path('views'),
            '/@can\(\s*\'([a-z0-9]+\.[a-z0-9._-]+)\'/'
        );

        $this->assertNotEmpty($used, '未扫描到任何 @can 指令，正则可能已失效');

        $known = Permission::query()->pluck('name')->all();
        $unknown = array_values(array_diff($used, $known));

        $this->assertSame([], $unknown, '视图 @can 引用了不存在的权限');
    }

    public function test_every_button_permission_is_reachable_by_some_role(): void
    {
        // 按钮级权限（形如 resource.action）至少应被 admin 持有，
        // 否则该按钮对任何人都不可见 —— 等于功能被静默废弃
        $buttons = Permission::query()
            ->where('name', 'like', '%.%')
            ->whereNotIn('name', [
                'dashboard.view', 'user.manage', 'post.manage', 'role.manage',
                'menu.manage', 'log.manage', 'dict.manage', 'settings.manage',
            ])
            ->pluck('name')
            ->all();

        $this->assertNotEmpty($buttons);

        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->firstOrFail();

        foreach ($buttons as $button) {
            $this->assertTrue(
                Gate::forUser($admin)->check($button),
                "按钮权限「{$button}」连 admin 都无法通过，应检查菜单树与角色授权"
            );
        }
    }
}
