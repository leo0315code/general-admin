<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 示例业务表：文章（Posts）
 * 作为后续业务 CRUD 的复制模板：标题、内容、状态、发布时间、软删除
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('作者');
            $table->string('title')->comment('标题');
            $table->text('content')->comment('内容');
            $table->string('status', 20)->default('draft')->comment('状态：draft=草稿 / published=已发布');
            $table->timestamp('published_at')->nullable()->comment('发布时间');
            $table->timestamps();
            $table->softDeletes(); // 软删除

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
