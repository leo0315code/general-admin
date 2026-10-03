<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 文章封面附件：让附件基座被业务真正使用
 *
 * nullOnDelete：附件被删（或 prune）时仅清空封面引用，不连带删除文章。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('cover_attachment_id')
                ->nullable()
                ->after('content')
                ->constrained('attachments')
                ->nullOnDelete()
                ->comment('封面附件（图片，私有盘，预览走鉴权路由）');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cover_attachment_id');
        });
    }
};
