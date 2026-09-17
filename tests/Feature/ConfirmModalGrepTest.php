<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 原生 confirm() 残留回归（UI 现代化重构 · T05 / SEC-1）
 *
 * 破坏性操作 100% 走统一 ConfirmModal（真实表单 + Alpine store），
 * 全仓 resources/views 内禁止再出现原生 confirm()。
 */
class ConfirmModalGrepTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_native_confirm_in_views(): void
    {
        $root = dirname(__DIR__, 2).'/resources/views';

        $this->assertDirectoryExists($root);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $hits = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $line = 1;

            foreach (file($file->getPathname()) ?: [] as $content) {
                if (str_contains($content, 'confirm(')) {
                    $hits[] = $file->getPathname().':'.$line;
                }

                $line++;
            }
        }

        $this->assertSame([], $hits, 'resources/views 内存在原生 confirm() 残留，请改用 x-confirm-modal：'.implode(', ', $hits));
    }
}
