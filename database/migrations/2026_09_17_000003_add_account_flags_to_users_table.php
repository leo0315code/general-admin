<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 账号启停 + 登录痕迹 + 首登强制改密
 *
 * - status：1 启用 / 0 停用（停用用户不能登录）
 * - last_login_at / last_login_ip：登录成功时记录
 * - must_change_password：1 表示登录后强制跳转改密页（导入/重置密码后置位）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('status')->default(true)->after('email_verified_at')->comment('账号状态：1启用 0停用');
            $table->timestamp('last_login_at')->nullable()->after('status')->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at')->comment('最后登录 IP');
            $table->boolean('must_change_password')->default(false)->after('last_login_ip')->comment('首次登录强制改密');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_login_at', 'last_login_ip', 'must_change_password']);
        });
    }
};
