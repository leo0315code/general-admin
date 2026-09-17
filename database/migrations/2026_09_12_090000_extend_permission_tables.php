<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie laravel-permission 表结构扩展：
 * - roles.description     角色描述（原自建 RBAC 字段，保留展示）
 * - permissions.label     权限中文展示名（Spatie 的 name 作为权限标识使用）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('name')->comment('角色描述');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name')->comment('权限中文展示名');
            $table->string('description')->nullable()->after('label')->comment('权限描述');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['label', 'description']);
        });
    }
};
