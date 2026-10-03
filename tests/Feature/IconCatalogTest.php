<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * 图标目录看护
 *
 * 背景：Vue 端 Icon.vue 用手工白名单 paths 渲染 SVG，一旦用到未登记的名称，
 * 就会静默渲染成空白图形（曾经的受害者：停用的 no-symbol、字典项的 list-bullet、
 * 超级管理员的 star、排序的 chevron-up-down、日志「创建」的 plus-circle …）。
 * Blade 端走 blade-heroicons 包（全量图标），不存在该问题。
 *
 * 本测试把「用到即必须登记」固化为回归用例。
 */
class IconCatalogTest extends TestCase
{
    /** Vue/JS 里用到的图标必须在 Icon.vue 的 paths 白名单中 */
    public function test_vue_icons_are_registered(): void
    {
        $source = File::get(resource_path('js/vue/components/Icon.vue'));

        preg_match_all("/'(heroicon-[a-z]-[a-z0-9-]+)'\s*:/", $source, $matches);
        $registered = array_unique($matches[1]);

        $this->assertNotEmpty($registered, '未能解析 Icon.vue 的图标表');

        $used = $this->scanIcons(resource_path('js'), ['vue', 'js']);
        $missing = array_values(array_diff($used, $registered));

        $this->assertSame(
            [],
            $missing,
            '以下图标在 Icon.vue 中未登记，页面会渲染成空白 SVG：'.implode(', ', $missing)
        );
    }

    /** 每个登记的图标都必须有合法的 path 数据（非空且以 moveto 开头） */
    public function test_registered_icons_have_path_data(): void
    {
        $source = File::get(resource_path('js/vue/components/Icon.vue'));

        preg_match_all("/'(heroicon-[a-z]-[a-z0-9-]+)'\s*:\s*\"([^\"]*)\"/", $source, $matches, PREG_SET_ORDER);
        $this->assertNotEmpty($matches, '未能解析 Icon.vue 的 path 数据');

        foreach ($matches as [, $name, $path]) {
            $this->assertNotSame('', trim($path), "图标 {$name} 的 path 为空");
            $this->assertMatchesRegularExpression('/^[Mm]/', trim($path), "图标 {$name} 的 path 不是合法的 SVG 路径");
        }
    }

    /** 未登记图标必须有兜底，不能静默空白 */
    public function test_icon_component_has_fallback(): void
    {
        $source = File::get(resource_path('js/vue/components/Icon.vue'));

        $this->assertStringContainsString('FALLBACK', $source);
        $this->assertStringContainsString('question-mark-circle', $source);
    }

    /** Blade 端用到的图标必须在 blade-heroicons 包中真实存在 */
    public function test_blade_icons_exist_in_package(): void
    {
        $svgDir = base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg');

        if (! File::isDirectory($svgDir)) {
            $this->markTestSkipped('blade-heroicons 包未安装');
        }

        foreach ($this->scanIcons(resource_path('views'), ['php']) as $icon) {
            // heroicon-o-user → o-user.svg
            [, $variant, $name] = explode('-', $icon, 3);
            $file = $svgDir.'/'.$variant.'-'.$name.'.svg';

            $this->assertFileExists($file, "Blade 使用的图标 {$icon} 在 blade-heroicons 包中不存在");
        }
    }

    /** 操作日志的每个动作词都要有对应图标（否则又出现「修改」没图标） */
    public function test_log_actions_have_icons(): void
    {
        $logger = File::get(app_path('Support/OperationLogger.php'));
        preg_match_all("/=>\s*\['[^']*',\s*'([^']+)'/u", $logger, $matches);
        preg_match_all("/'(?:POST|PUT|PATCH|DELETE|GET)'\s*=>\s*'([^']+)'/u", $logger, $methodMatches);

        $actions = array_unique(array_merge($matches[1], $methodMatches[1]));
        $this->assertNotEmpty($actions);

        $logs = File::get(resource_path('js/vue/components/LogsIndex.vue'));
        preg_match('/const ACTION_STYLES = \{(.*?)\n\};/us', $logs, $block);
        $this->assertNotEmpty($block[1] ?? '', '未能解析 LogsIndex.vue 的 ACTION_STYLES');

        preg_match_all('/^\s{4}([\x{4e00}-\x{9fa5}]+):/mu', $block[1], $styled);
        $styled = $styled[1];

        foreach ($actions as $action) {
            // 「登录失败」这类由 includes('失败') 统一兜底为危险色 + x-circle
            if (str_contains($action, '失败')) {
                continue;
            }

            $this->assertContains(
                $action,
                $styled,
                "操作类型「{$action}」在 LogsIndex.vue 中没有配色与图标，需补进 ACTION_STYLES"
            );
        }
    }

    /**
     * 扫描目录下源码文件里出现的图标名
     *
     * @param  list<string>  $extensions
     * @return list<string>
     */
    private function scanIcons(string $directory, array $extensions): array
    {
        $icons = [];

        foreach (File::allFiles($directory) as $file) {
            if (! in_array($file->getExtension(), $extensions, true)) {
                continue;
            }

            preg_match_all('/heroicon-[a-z]-[a-z0-9-]+/', File::get($file->getRealPath()), $matches);

            foreach ($matches[0] as $icon) {
                $icons[$icon] = true;
            }
        }

        $icons = array_keys($icons);
        sort($icons);

        return $icons;
    }
}
