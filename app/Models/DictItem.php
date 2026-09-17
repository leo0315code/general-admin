<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 字典项模型（属于某个字典类型）
 */
class DictItem extends Model
{
    use HasFactory;

    /** @var list<string> 允许批量赋值的字段 */
    protected $fillable = ['dict_type_id', 'label', 'value', 'sort', 'status', 'remark'];

    /** @return array<string, string> 字段类型转换 */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /** 所属字典类型 */
    public function dictType(): BelongsTo
    {
        return $this->belongsTo(DictType::class);
    }
}
