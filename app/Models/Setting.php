<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 系统设置模型（key/value 键值对）
 */
class Setting extends Model
{
    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = ['key', 'value', 'label'];

    public $incrementing = false;

    protected $primaryKey = 'key';
}
