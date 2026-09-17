<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 系统设置控制器：站点名称 / 每页条数 / 版权信息
 * （仅 admin 角色可访问，路由由 role:admin 保护）
 */
class SettingController extends Controller
{
    /** 可配置项及其默认值 */
    private const DEFAULTS = [
        'site_name' => ['label' => '站点名称', 'default' => '通用管理后台'],
        'pagination' => ['label' => '列表每页条数', 'default' => '15'],
        'copyright' => ['label' => '版权信息', 'default' => ''],
    ];

    public function index(): View
    {
        $settings = Setting::query()->pluck('value', 'key');

        $fields = collect(self::DEFAULTS)->map(function ($meta, $key) use ($settings) {
            return [
                'key' => $key,
                'label' => $meta['label'],
                'value' => $settings->has($key) ? $settings[$key] : $meta['default'],
            ];
        })->values();

        return view('settings.index', compact('fields'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'pagination' => ['required', 'integer', 'between:5,100'],
            'copyright' => ['nullable', 'string', 'max:200'],
        ], [
            'site_name.required' => '请输入站点名称。',
            'pagination.between' => '每页条数需在 5-100 之间。',
        ]);

        foreach ($validated as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'label' => self::DEFAULTS[$key]['label']]
            );
        }

        return redirect()
            ->route('settings.index')
            ->with('success', '系统设置已保存。');
    }
}
