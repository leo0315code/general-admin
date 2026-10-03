<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 群发消息记录表（主动发送站内通知）
 *
 * 只记「这一批发了什么、发给谁范围、多少人、是否撤回」，
 * 真正的接收人明细在 notifications 表（每条一行，broadcast_id 回指本表）。
 * 撤回 = 把该 broadcast_id 下的通知删掉，并在本表打 revoked_at。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            // 发送人置空也要保留历史，故用 nullOnDelete
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('发送人');
            $table->string('type', 50)->default('message.custom')->comment('消息类型');
            $table->string('title', 255)->comment('标题');
            $table->text('content')->nullable()->comment('正文');
            $table->string('link', 500)->nullable()->comment('站内跳转地址');
            $table->string('scope', 20)->comment('发送范围：users / role / all');
            $table->string('role', 50)->nullable()->comment('scope=role 时的角色名');
            $table->unsignedInteger('recipients_count')->default(0)->comment('实际写入的接收人数');
            $table->timestamp('revoked_at')->nullable()->comment('撤回时间（null = 未撤回）');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_broadcasts');
    }
};
