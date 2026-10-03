<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 附件表（上传基座）
 *
 * 文件本体放在私有磁盘（storage/app/private），**不进 public 目录**：
 * 浏览器无法直接请求到，也就不会被执行（上传 .php/.html 也无法挂马）。
 * 下载统一走带鉴权的路由，由控制器读取后以 attachment 响应输出。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('上传者');
            $table->string('disk', 32)->default('local')->comment('存储磁盘');
            $table->string('path', 500)->comment('磁盘内相对路径');
            $table->string('name', 255)->comment('原始文件名（仅用于下载展示，不作为存储名）');
            $table->string('extension', 16)->comment('扩展名（小写，白名单内）');
            $table->string('mime', 100)->comment('检测到的 MIME 类型');
            $table->unsignedBigInteger('size')->comment('字节数');
            $table->char('checksum', 64)->nullable()->comment('文件 sha256（排错/去重用）');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
