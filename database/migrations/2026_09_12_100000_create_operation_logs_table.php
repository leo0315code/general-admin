<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 操作日志表：登录审计（登录成功/失败/登出）+ 后台写操作审计（增删改）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作用户 ID');
            $table->string('username', 100)->nullable()->comment('用户名（冗余存储，用户删除后仍可追溯）');
            $table->string('method', 10)->comment('请求方法');
            $table->string('module', 50)->nullable()->comment('模块');
            $table->string('action', 50)->comment('操作类型（登录/退出/增/删/改…）');
            $table->string('description', 255)->nullable()->comment('操作描述');
            $table->string('ip', 45)->nullable()->comment('IP 地址');
            $table->string('user_agent', 500)->nullable()->comment('User-Agent');
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
