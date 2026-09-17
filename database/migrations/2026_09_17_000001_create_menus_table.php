<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 菜单 / 权限节点表（参考 BuildAdmin 的权限节点模型）
 *
 * 一棵树同时承载三种节点，做到「菜单即权限」：
 * - dir    目录：仅用于侧边栏分组，不参与鉴权（如「系统管理」）
 * - menu   菜单：对应一个页面，permission_name 决定该菜单是否可见 / 可访问
 * - button 按钮权限：对应页面内的操作点（如「新增用户」），用于细粒度控制
 *
 * permission_name 与 spatie/laravel-permission 的 permissions.name 一一对应，
 * 由 MenuController 在保存 / 删除时自动双向同步，避免菜单与权限两张表脱节。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0)->comment('父级 ID（0 = 顶级）');
            $table->string('type', 10)->default('menu')->comment('节点类型：dir 目录 / menu 菜单 / button 按钮权限');
            $table->string('title', 50)->comment('显示名称');
            $table->string('permission_name', 100)->nullable()->comment('权限标识（对应 permissions.name）');
            $table->string('icon', 50)->nullable()->comment('图标（heroicon 名称，如 heroicon-o-users）');
            $table->string('route', 100)->nullable()->comment('路由名（如 users.index；目录与按钮为空）');
            $table->integer('sort')->default(0)->comment('排序（越小越靠前）');
            $table->boolean('status')->default(true)->comment('状态：1 启用 / 0 停用');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();

            $table->unique('permission_name');
            $table->index(['pid', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
