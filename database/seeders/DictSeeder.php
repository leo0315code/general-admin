<?php

namespace Database\Seeders;

use App\Models\DictItem;
use App\Models\DictType;
use Illuminate\Database\Seeder;

/**
 * 数据字典种子：字典类型 + 字典项（按 type + value 幂等，可重复执行）
 *
 * 字典项顺序由 sort 控制；status 用于停用旧选项而不删除。
 */
class DictSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->types() as $type) {
            $items = $type['items'] ?? [];
            unset($type['items']);

            $dictType = DictType::query()->updateOrCreate(
                ['type' => $type['type']],
                $type
            );

            foreach ($items as $sort => $item) {
                DictItem::query()->updateOrCreate(
                    ['dict_type_id' => $dictType->id, 'value' => $item['value']],
                    array_merge($item, ['sort' => ($sort + 1) * 10])
                );
            }
        }
    }

    /**
     * 字典类型与字典项清单
     *
     * @return list<array<string, mixed>>
     */
    protected function types(): array
    {
        return [
            [
                'name' => '文章状态',
                'type' => 'post_status',
                'description' => '文章模块的发布状态',
                'status' => true,
                'items' => [
                    ['label' => '草稿', 'value' => 'draft', 'remark' => '仅后台可见，未对外发布'],
                    ['label' => '已发布', 'value' => 'published', 'remark' => '对外可见'],
                    ['label' => '已下线', 'value' => 'offline', 'remark' => '曾发布，已撤回'],
                ],
            ],
            [
                'name' => '用户性别',
                'type' => 'user_gender',
                'description' => '用户基础资料：性别',
                'status' => true,
                'items' => [
                    ['label' => '未知', 'value' => '0', 'remark' => '默认值，用户未填写'],
                    ['label' => '男', 'value' => '1'],
                    ['label' => '女', 'value' => '2'],
                ],
            ],
            [
                'name' => '通用状态',
                'type' => 'common_status',
                'description' => '通用启用 / 停用状态，可复用于任意模块',
                'status' => true,
                'items' => [
                    ['label' => '启用', 'value' => '1', 'remark' => '正常可用'],
                    ['label' => '停用', 'value' => '0', 'remark' => '保留数据但不可用'],
                ],
            ],
            [
                'name' => '审核状态',
                'type' => 'audit_status',
                'description' => '内容 / 申请的审核流转状态',
                'status' => true,
                'items' => [
                    ['label' => '待审核', 'value' => 'pending'],
                    ['label' => '已通过', 'value' => 'approved'],
                    ['label' => '已驳回', 'value' => 'rejected', 'remark' => '需填写驳回原因'],
                ],
            ],
            [
                'name' => '通知渠道',
                'type' => 'notify_channel',
                'description' => '消息通知的发送方式',
                'status' => true,
                'items' => [
                    ['label' => '站内信', 'value' => 'site'],
                    ['label' => '邮件', 'value' => 'mail'],
                    ['label' => '短信', 'value' => 'sms'],
                ],
            ],
            [
                'name' => '字典示例（已停用）',
                'type' => 'demo_disabled',
                'description' => '演示停用状态：类型停用后其字典项不参与业务下拉',
                'status' => false,
                'items' => [
                    ['label' => '示例项A', 'value' => 'a', 'status' => false],
                    ['label' => '示例项B', 'value' => 'b'],
                ],
            ],
        ];
    }
}
