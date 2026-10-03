<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 通知回指所属群发：单条主动发送的通知也可以被整体撤回 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // 广播记录被清理时通知本身保留，故用 nullOnDelete
            $table->foreignId('broadcast_id')
                ->nullable()
                ->after('user_id')
                ->constrained('notification_broadcasts')
                ->nullOnDelete()
                ->comment('所属群发（null = 系统事件通知）');

            // 撤回的主查询路径
            $table->index('broadcast_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['broadcast_id']);
            $table->dropConstrainedForeignId('broadcast_id');
        });
    }
};
