<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * 菜单 / 权限节点模型（参考 BuildAdmin 的权限节点设计）
 *
 * 三种节点类型共用一棵树（menus 表）：
 * - dir    目录：侧边栏分组标题，不鉴权
 * - menu   菜单：可导航页面，permission_name 控制可见与访问
 * - button 按钮权限：页面内操作点，用 @can('users.create') 控制显隐
 *
 * permission_name 与 spatie permissions.name 同名，由 MenuController 同步。
 */
class Menu extends Model
{
    /** 目录节点 */
    public const TYPE_DIR = 'dir';

    /** 菜单节点 */
    public const TYPE_MENU = 'menu';

    /** 按钮权限节点 */
    public const TYPE_BUTTON = 'button';

    /** 节点类型中文名（视图展示用） */
    public const TYPE_LABELS = [
        self::TYPE_DIR => '目录',
        self::TYPE_MENU => '菜单',
        self::TYPE_BUTTON => '按钮',
    ];

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'pid',
        'type',
        'title',
        'permission_name',
        'icon',
        'route',
        'sort',
        'status',
        'remark',
    ];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'pid' => 'integer',
            'sort' => 'integer',
            'status' => 'boolean',
        ];
    }

    /** 父节点 */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pid');
    }

    /** 子节点（按排序） */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'pid')->orderBy('sort')->orderBy('id');
    }

    /** 仅启用节点 */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /** 按排序（越小越靠前） */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('id');
    }

    public function isDir(): bool
    {
        return $this->type === self::TYPE_DIR;
    }

    public function isMenu(): bool
    {
        return $this->type === self::TYPE_MENU;
    }

    public function isButton(): bool
    {
        return $this->type === self::TYPE_BUTTON;
    }

    /** 类型中文名 */
    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /** 是否可在侧边栏导航（目录 / 菜单） */
    public function isNavigable(): bool
    {
        return in_array($this->type, [self::TYPE_DIR, self::TYPE_MENU], true);
    }

    /**
     * 菜单激活匹配模式：users.index → users.*、dashboard → dashboard。
     * 用于列表页与详情页（create/edit）都保持菜单高亮。
     */
    public function activePattern(): ?string
    {
        if (! $this->route) {
            return null;
        }

        return Str::contains($this->route, '.')
            ? Str::before($this->route, '.').'.*'
            : $this->route;
    }

    /** 该菜单在侧边栏是否处于激活态 */
    public function isActive(): bool
    {
        $pattern = $this->activePattern();

        return $pattern !== null && request()->routeIs($pattern);
    }

    /**
     * 构建嵌套菜单树。
     *
     * @param  Collection<int, self>|null  $nodes  给定节点集合（不传则查全表）
     * @return Collection<int, self>
     */
    public static function tree(?Collection $nodes = null): Collection
    {
        $nodes ??= static::query()->ordered()->get();

        return static::nest($nodes);
    }

    /**
     * 递归挂载 children 关联（内存构树，避免 N+1）。
     *
     * @param  Collection<int, self>  $nodes
     * @return Collection<int, self>
     */
    protected static function nest(Collection $nodes, int $pid = 0): Collection
    {
        return $nodes->where('pid', $pid)->values()->map(function (self $node) use ($nodes) {
            $node->setRelation('children', static::nest($nodes, $node->id));

            return $node;
        });
    }

    /**
     * 扁平化树（含深度），用于下拉选择与列表渲染。
     *
     * @param  Collection<int, self>|null  $nodes
     * @return list<array{menu: self, depth: int}>
     */
    public static function flatten(?Collection $nodes = null): array
    {
        $result = [];
        static::walk(static::tree($nodes), 0, $result);

        return $result;
    }

    /**
     * 深度优先遍历已构树的节点。
     *
     * @param  Collection<int, self>  $nodes
     * @param  list<array{menu: self, depth: int}>  $result
     */
    protected static function walk(Collection $nodes, int $depth, array &$result): void
    {
        foreach ($nodes as $node) {
            $result[] = ['menu' => $node, 'depth' => $depth];
            static::walk($node->children, $depth + 1, $result);
        }
    }

    /** 该节点自身 + 全部后代 ID（用于禁止把自己设为自己的父级） */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * 同步对应的 spatie 权限记录（「菜单即权限」的核心）
     *
     * 菜单的 permission_name 即 permissions.name，title 即权限中文名（label），
     * 保存菜单时自动落库，从而在「菜单管理」一处即可完成权限维护。
     */
    public function syncPermission(): void
    {
        if (blank($this->permission_name)) {
            return;
        }

        $permission = Permission::findOrCreate($this->permission_name);
        $permission->update([
            'label' => $this->title,
            'description' => $this->remark ?: $permission->description,
        ]);
    }

    /** 对应的权限记录（未配置权限标识或记录已不存在时返回 null） */
    public function permission(): ?Permission
    {
        if (blank($this->permission_name)) {
            return null;
        }

        return Permission::query()->where('name', $this->permission_name)->first();
    }

    /** 对应权限是否已分配给角色（删除菜单时需要保护） */
    public function permissionIsInUse(): bool
    {
        return (bool) $this->permission()?->roles()->exists();
    }
}
