<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuRequest;
use App\Http\Requests\UpdateMenuRequest;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/**
 * 菜单管理控制器（参考 BuildAdmin 的权限节点模型）
 *
 * 本项目「菜单即权限」：本控制器是菜单树与 spatie 权限表的唯一同步入口——
 * - 新建 / 编辑菜单 → 自动 findOrCreate 对应权限并同步中文名（label）
 * - 权限标识变更 → 旧权限未被角色使用时自动清理，避免残留幽灵权限
 * - 删除菜单 → 有子节点或权限已被角色引用时拒绝删除
 */
class MenuController extends Controller
{
    /** 菜单树列表（目录 / 菜单 / 按钮 三种节点，按层级缩进展示） */
    public function index(): View
    {
        return view('menus.index', [
            'rows' => Menu::flatten(),
        ]);
    }

    /** 新建菜单表单 */
    public function create(Request $request): View
    {
        return view('menus.create', [
            'parents' => $this->parentOptions(),
            'menu' => null,
            'selectedPid' => (int) $request->query('pid', 0),
            'selectedType' => (string) $request->query('type', Menu::TYPE_MENU),
            'routeSuggestions' => $this->routeSuggestions(),
        ]);
    }

    /** 保存菜单并同步权限记录 */
    public function store(StoreMenuRequest $request): RedirectResponse
    {
        $menu = DB::transaction(function () use ($request): Menu {
            $menu = Menu::query()->create($this->payload($request));
            $menu->syncPermission();

            return $menu;
        });

        return redirect()
            ->route('menus.index')
            ->with('success', "菜单「{$menu->title}」创建成功。");
    }

    /** 编辑菜单表单 */
    public function edit(Menu $menu): View
    {
        return view('menus.edit', [
            'menu' => $menu,
            'parents' => $this->parentOptions($menu),
            'selectedPid' => $menu->pid,
            'selectedType' => $menu->type,
            'routeSuggestions' => $this->routeSuggestions(),
        ]);
    }

    /** 更新菜单并同步权限记录（含旧权限清理） */
    public function update(UpdateMenuRequest $request, Menu $menu): RedirectResponse
    {
        $oldPermissionName = $menu->permission_name;
        $warning = null;

        DB::transaction(function () use ($request, $menu, $oldPermissionName, &$warning): void {
            $menu->update($this->payload($request));
            $menu->syncPermission();

            if (! $oldPermissionName || $oldPermissionName === $menu->permission_name) {
                return;
            }

            // 权限标识已改名：旧权限未被角色使用则删除，避免出现"幽灵权限"
            $oldPermission = Permission::query()->where('name', $oldPermissionName)->first();

            if (! $oldPermission) {
                return;
            }

            if ($oldPermission->roles()->exists()) {
                $warning = "旧权限「{$oldPermissionName}」已分配给角色，未自动删除；确认不再需要后在角色授权中取消勾选。";

                return;
            }

            $oldPermission->delete();
        });

        $redirect = redirect()
            ->route('menus.index')
            ->with('success', "菜单「{$menu->title}」更新成功。");

        return $warning ? $redirect->with('error', $warning) : $redirect;
    }

    /** 删除菜单（同步删除对应权限记录） */
    public function destroy(Menu $menu): RedirectResponse
    {
        if ($menu->children()->exists()) {
            return redirect()
                ->route('menus.index')
                ->with('error', "「{$menu->title}」下还有子节点，请先删除子节点。");
        }

        if ($menu->permissionIsInUse()) {
            return redirect()
                ->route('menus.index')
                ->with('error', "「{$menu->title}」对应的权限已分配给角色，请先在角色管理中解除授权。");
        }

        DB::transaction(function () use ($menu): void {
            $permission = $menu->permission();
            $menu->delete();

            if ($permission && ! $permission->roles()->exists()) {
                $permission->delete();
            }
        });

        return redirect()
            ->route('menus.index')
            ->with('success', "菜单「{$menu->title}」已删除。");
    }

    /** 启用 / 停用菜单（停用后不出现在侧边栏，权限仍然保留） */
    public function toggleStatus(Menu $menu): RedirectResponse
    {
        $menu->update(['status' => ! $menu->status]);

        return redirect()
            ->route('menus.index')
            ->with('success', "菜单「{$menu->title}」已".($menu->status ? '启用' : '停用').'。');
    }

    /** 表单提交数据（统一空值处理） */
    protected function payload(Request $request): array
    {
        return [
            'pid' => (int) $request->validated('pid'),
            'type' => (string) $request->validated('type'),
            'title' => (string) $request->validated('title'),
            'permission_name' => $request->validated('permission_name') ?: null,
            'icon' => $request->validated('icon') ?: null,
            'route' => $request->validated('route') ?: null,
            'sort' => (int) ($request->validated('sort') ?? 0),
            'status' => $request->boolean('status'),
            'remark' => $request->validated('remark') ?: null,
        ];
    }

    /**
     * 上级节点候选项（目录 / 菜单），编辑时排除自身与全部后代。
     *
     * @return list<array{menu: Menu, depth: int}>
     */
    protected function parentOptions(?Menu $exclude = null): array
    {
        $excluded = $exclude ? $exclude->descendantIds() : [];

        return array_values(array_filter(
            Menu::flatten(),
            fn (array $row): bool => $row['menu']->isNavigable()
                && ! in_array($row['menu']->id, $excluded, true)
        ));
    }

    /**
     * 可绑定的路由名建议（后台前缀下的命名路由），供表单下拉选择。
     *
     * @return list<string>
     */
    protected function routeSuggestions(): array
    {
        $prefix = (string) config('app.admin_prefix');
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! str_starts_with($route->uri(), $prefix.'/')) {
                continue;
            }

            $names[$name] = true;
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }
}
