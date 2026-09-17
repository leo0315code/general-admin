<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 列表查询索引（UI 现代化重构 · T02 / PERF-2）
 *
 * 纯增量加索引，不动任何表结构，sqlite in-memory 测试同样兼容（标准 $table->index()）。
 * 覆盖三类高频列表查询：
 * - 软删除列表：WHERE deleted_at IS NULL ORDER BY created_at DESC → (deleted_at, created_at)
 * - 状态筛选：  WHERE status=? AND deleted_at IS NULL              → (status, deleted_at)
 * - 日志筛选：  WHERE action=? AND created_at BETWEEN …           → (action, created_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['deleted_at', 'created_at'], 'users_del_created_idx');
            $table->index(['status', 'deleted_at'], 'users_status_del_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->index(['deleted_at', 'created_at'], 'posts_del_created_idx');
            $table->index(['status', 'deleted_at'], 'posts_status_del_idx');
        });

        Schema::table('operation_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at'], 'logs_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_del_created_idx');
            $table->dropIndex('users_status_del_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_del_created_idx');
            $table->dropIndex('posts_status_del_idx');
        });

        Schema::table('operation_logs', function (Blueprint $table) {
            $table->dropIndex('logs_action_created_idx');
        });
    }
};
