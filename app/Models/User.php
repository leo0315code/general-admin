<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * 用户模型
 *
 * 基于 spatie/laravel-permission 的 RBAC：
 * - HasRoles trait 提供 assignRole / syncRoles / hasRole / hasPermissionTo / getRoleNames 等
 * - admin 角色（超级管理员）拥有全部权限，由 AuthServiceProvider 的 Gate::before 短路判定
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /** 内置 admin 角色标识（超级管理员，拥有全部权限） */
    public const ROLE_ADMIN = 'admin';

    /** 内置 editor 角色标识（编辑，仅内容管理） */
    public const ROLE_EDITOR = 'editor';

    /** 账号启用状态值 */
    public const STATUS_ACTIVE = 1;

    /** 账号停用状态值 */
    public const STATUS_DISABLED = 0;

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'status',
        'last_login_at',
        'last_login_ip',
        'must_change_password',
    ];

    /** @var list<string> 序列化时隐藏的字段 */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /** 用户发布的文章（一对多） */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** 返回用户角色名列表（Spatie getRoleNames 集合的封装，用于视图/接口展示） */
    public function roleNames(): array
    {
        return $this->getRoleNames()->all();
    }

    /** 是否为超级管理员 */
    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /** 账号是否处于启用状态 */
    public function isActive(): bool
    {
        return (bool) $this->status;
    }
}
