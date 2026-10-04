<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * 状态徽章一致性看护
 *
 * 背景：此前「已发布/草稿、启用/停用、角色、待改密」这类状态标签在 5+ 个 Vue 列表页里
 * 各写一份 `rounded-full ... bg-*-100 text-*-700 dark:...` 长串，改配色要逐个文件改，
 * 漏改就出现同状态不同色。现已统一收敛到 StatusBadge.vue（对齐 Blade 的 x-status-badge）。
 *
 * 本测试把「不得再硬编码、两端配色必须一致、用了就必须 import」固化为回归用例。
 */
class BadgeConsistencyTest extends TestCase
{
    /** 五种语义色的配色串（Blade 端为基准） */
    private const TYPES = ['success', 'warning', 'danger', 'info', 'neutral'];

    /** Vue 组件里不得再出现硬编码的状态徽章配色 */
    public function test_vue_components_do_not_hardcode_badge_styles(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('js/vue/components')) as $file) {
            if ($file->getFilename() === 'StatusBadge.vue') {
                continue;
            }

            foreach (File::lines($file->getRealPath()) as $number => $line) {
                if (! str_contains($line, 'rounded-full')) {
                    continue;
                }

                // 徽章特征：同一行同时出现「浅底 bg-*-100」与「深字 text-*-700」
                // （圆点、头像等只有 bg-*-500 的圆形元素不算徽章，不在收敛范围）
                if (
                    preg_match('/bg-(success|warning|danger|info)-100/', $line)
                    && preg_match('/text-(success|warning|danger|info)-700/', $line)
                ) {
                    $offenders[] = $file->getFilename().':'.($number + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            '以下位置仍在硬编码状态徽章配色，请改用 <StatusBadge>：'.implode(', ', $offenders)
        );
    }

    /** StatusBadge.vue 与 x-status-badge 的配色必须逐项一致 */
    public function test_vue_and_blade_badge_palettes_match(): void
    {
        $vue = File::get(resource_path('js/vue/components/StatusBadge.vue'));
        $blade = File::get(resource_path('views/components/status-badge.blade.php'));

        foreach (self::TYPES as $type) {
            // 取出 Blade 的 'success' => '...' 配色串
            preg_match("/'{$type}'\s*=>\s*'([^']+)'/", $blade, $bladeMatch);
            preg_match("/\n\s+{$type}:\s*'([^']+)'/", $vue, $vueMatch);

            $this->assertNotSame('', $bladeMatch[1] ?? '', "Blade 端缺少 {$type} 配色");
            $this->assertNotSame('', $vueMatch[1] ?? '', "Vue 端缺少 {$type} 配色");
            $this->assertSame(
                $bladeMatch[1],
                $vueMatch[1],
                "{$type} 徽章在 Vue 与 Blade 两端配色不一致"
            );
        }
    }

    /** 用到 <StatusBadge> 的组件必须 import（无全局注册，漏 import 会静默渲染成未知标签） */
    public function test_components_using_badge_import_it(): void
    {
        foreach (File::allFiles(resource_path('js/vue/components')) as $file) {
            if ($file->getFilename() === 'StatusBadge.vue') {
                continue;
            }

            $source = File::get($file->getRealPath());

            if (! str_contains($source, '<StatusBadge')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                "/import\s+StatusBadge\s+from\s+'\.\/StatusBadge\.vue'/",
                $source,
                $file->getFilename().' 使用了 <StatusBadge> 但未 import'
            );
        }
    }

    /** 源码里用到的每个语义色阶都必须在 @theme 中登记（否则工具类静默不生成） */
    public function test_used_semantic_shades_are_defined(): void
    {
        $css = File::get(resource_path('css/app.css'));

        preg_match_all('/--color-([a-z]+-\d+)\s*:/', $css, $matches);
        $defined = array_unique($matches[1]);

        $offenders = [];

        foreach ([resource_path('views'), resource_path('js')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (! in_array($file->getExtension(), ['php', 'vue', 'js'], true)) {
                    continue;
                }

                foreach (File::lines($file->getRealPath()) as $line) {
                    preg_match_all(
                        '/\b[a-z]+-((?:primary|success|warning|danger|info)-\d+)\b/',
                        $line,
                        $used
                    );

                    foreach (array_unique($used[1]) as $shade) {
                        if (! in_array($shade, $defined, true)) {
                            $offenders[$shade] = true;
                        }
                    }
                }
            }
        }

        $offenders = array_keys($offenders);
        sort($offenders);

        $this->assertSame(
            [],
            $offenders,
            '以下语义色阶在 app.css 的 @theme 中未定义，相关工具类不会生成：'.implode(', ', $offenders)
        );
    }

    /** 危险语义统一走 danger token，不得再写裸 red-* 色 */
    public function test_danger_semantics_use_tokens(): void
    {
        $offenders = [];

        foreach ([resource_path('views'), resource_path('js')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (! in_array($file->getExtension(), ['php', 'vue', 'js'], true)) {
                    continue;
                }

                foreach (File::lines($file->getRealPath()) as $number => $line) {
                    if (preg_match('/\b(bg|text|border|ring|from|to|fill|stroke)-red-\d/', $line)) {
                        $offenders[] = $file->getRelativePathname().':'.($number + 1);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            '以下位置仍用裸 red-* 色，请改用 danger-* 语义 token：'.implode(', ', $offenders)
        );
    }

    /** 危险按钮统一用 btn-danger-outline，不得再叠加裸色边框 */
    public function test_danger_buttons_use_shared_class(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('.btn-danger-outline', $css);

        // 类名一旦改动，调用方必须同步（否则按钮静默退化成无边框幽灵样式）
        $callers = 0;

        foreach ([resource_path('views'), resource_path('js')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (in_array($file->getExtension(), ['php', 'vue'], true)) {
                    $callers += substr_count(File::get($file->getRealPath()), 'btn-danger-outline');
                }
            }
        }

        $this->assertGreaterThan(0, $callers, 'btn-danger-outline 已无人使用，属死代码');
    }
}
