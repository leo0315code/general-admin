<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 字典类型模型（如：订单状态 / 支付方式）
 */
class DictType extends Model
{
    use HasFactory;

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = ['name', 'type', 'description', 'status'];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /** 该类型下的字典项 */
    public function items(): HasMany
    {
        return $this->hasMany(DictItem::class)->orderBy('sort')->orderBy('id');
    }
}
