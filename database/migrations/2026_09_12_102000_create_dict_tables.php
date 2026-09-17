<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 数据字典：字典类型（dict_types）+ 字典项（dict_items）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dict_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('字典类型名称');
            $table->string('type', 100)->unique()->comment('字典类型标识（英文，如 order_status）');
            $table->string('description', 255)->nullable()->comment('描述');
            $table->boolean('status')->default(true)->comment('状态：1启用 0停用');
            $table->timestamps();
        });

        Schema::create('dict_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dict_type_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100)->comment('字典项名称（中文）');
            $table->string('value', 100)->comment('字典项值');
            $table->integer('sort')->default(0)->comment('排序（越小越靠前）');
            $table->boolean('status')->default(true)->comment('状态：1启用 0停用');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();

            $table->unique(['dict_type_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dict_items');
        Schema::dropIfExists('dict_types');
    }
};
