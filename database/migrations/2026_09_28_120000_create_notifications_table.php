<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 站内通知表（通知中心）
 *
 * 与 Laravel 自带的 notifications（database channel）不同：
 * 本表是面向后台的轻量站内消息，字段直白（title/content/link），
 * 便于列表展示与按未读聚合，无需解析多态 notifiable + data JSON。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('接收用户');
            $table->string('type', 50)->comment('事件类型：account.created / account.password_reset / users.imported');
            $table->string('title', 255)->comment('标题');
            $table->text('content')->nullable()->comment('正文');
            $table->string('link', 500)->nullable()->comment('点击跳转的站内地址');
            $table->timestamp('read_at')->nullable()->comment('已读时间（null = 未读）');
            $table->timestamps();

            // 未读列表与「我的通知」分页的主查询路径
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
