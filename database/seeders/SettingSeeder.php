<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * 系统设置种子：写入初始配置项（按 key 幂等，不覆盖已改过管理后台的值）
 *
 * 说明：SettingController 内部有 DEFAULTS 回落，缺行时页面仍可渲染；
 * 本 Seeder 的作用是让设置表有初始数据、并给新环境一个明确基线。
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'site_name' => ['label' => '站点名称', 'value' => '通用管理后台'],
            'pagination' => ['label' => '列表每页条数', 'value' => '15'],
            'copyright' => ['label' => '版权信息', 'value' => '© '.date('Y').' 通用管理后台 · All rights reserved.'],
        ];

        foreach ($settings as $key => $meta) {
            // 已存在则完全不动：避免覆盖管理员在后台改过的值
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['label' => $meta['label'], 'value' => $meta['value']]
            );
        }
    }
}
