<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\Post;
use App\Support\Uploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 清理附件：过期记录 + 磁盘孤儿文件
 *
 * 为什么要这个命令：附件一旦上传就不再有人引用（当前尚无业务模块挂载），
 * 磁盘只增不减。两类垃圾都要清：
 * 1. 过期附件记录（超过保留天数）——连记录带文件一起删；
 * 2. 磁盘孤儿文件——有文件但数据库没记录（写库回滚失败、手工拷入等残留）。
 *
 * 用法：
 *   php artisan attachments:prune                  # 按 config('uploads.prune_days') 清理
 *   php artisan attachments:prune --days=30        # 指定保留天数
 *   php artisan attachments:prune --dry-run        # 只报告不删除（先跑这个确认影响面）
 *   php artisan attachments:prune --orphans-only   # 只清孤儿文件，不动数据库记录
 */
class PruneAttachments extends Command
{
    /** @var string */
    protected $signature = 'attachments:prune
                            {--days= : 保留天数，早于该天数的附件会被清理（默认取 config uploads.prune_days）}
                            {--dry-run : 只报告将要删除的内容，不实际删除}
                            {--orphans-only : 只清理磁盘孤儿文件（无数据库记录的文件）}';

    /** @var string */
    protected $description = '清理过期附件与磁盘孤儿文件（默认先 --dry-run 确认影响面）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orphansOnly = (bool) $this->option('orphans-only');
        $days = $this->resolveDays();

        if ($dryRun) {
            $this->warn('DRY RUN：以下为将要删除的内容，实际不会删除。');
        }

        $orphanCount = $this->pruneOrphans($dryRun);

        if ($orphansOnly) {
            $this->info("孤儿文件 {$orphanCount} 个".($dryRun ? '（未删除）' : '已删除').'。');

            return self::SUCCESS;
        }

        $this->info("保留天数：{$days} 天");

        // 被业务引用的附件（如文章封面）永不按保留期清理：
        // prune 只清「没人用的过期文件」，引用中的文件归业务生命周期管。
        $referencedIds = Post::query()
            ->whereNotNull('cover_attachment_id')
            ->pluck('cover_attachment_id')
            ->all();

        $query = Attachment::query()
            ->where('created_at', '<', now()->subDays($days))
            ->when($referencedIds, fn ($q) => $q->whereNotIn('id', $referencedIds));

        $total = (clone $query)->count();
        $bytes = (int) (clone $query)->sum('size');

        if ($total === 0) {
            $this->info('没有过期附件。');

            return self::SUCCESS;
        }

        $this->info("过期附件 {$total} 个，共 ".Uploader::humanSize($bytes).($dryRun ? '（未删除）' : ''));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $deleted = 0;

        // 分块处理，避免一次性把大量记录载入内存
        $query->chunkById(200, function ($attachments) use (&$deleted) {
            foreach ($attachments as $attachment) {
                Uploader::delete($attachment);
                $deleted++;
            }
        });

        Log::info('附件清理完成', ['deleted' => $deleted, 'orphans' => $orphanCount, 'days' => $days]);
        $this->info("已删除 {$deleted} 个过期附件、{$orphanCount} 个孤儿文件。");

        return self::SUCCESS;
    }

    /** 删除磁盘上有、数据库里没有的文件（只扫描附件目录，不越界） */
    private function pruneOrphans(bool $dryRun): int
    {
        $disk = (string) config('uploads.disk');
        $prefix = trim((string) config('uploads.prefix'), '/');
        $storage = Storage::disk($disk);

        $files = $storage->allFiles($prefix);

        if ($files === []) {
            return 0;
        }

        $known = Attachment::query()
            ->where('disk', $disk)
            ->pluck('path')
            ->flip()
            ->all();

        $orphans = array_values(array_filter($files, fn (string $path) => ! isset($known[$path])));

        if ($orphans === []) {
            $this->info('没有孤儿文件。');

            return 0;
        }

        $this->warn('孤儿文件 '.count($orphans).' 个：');
        foreach (array_slice($orphans, 0, 10) as $path) {
            $this->line('  - '.$path);
        }

        if (count($orphans) > 10) {
            $this->line('  … 其余 '.(count($orphans) - 10).' 个略');
        }

        if ($dryRun) {
            return count($orphans);
        }

        $storage->delete($orphans);

        return count($orphans);
    }

    private function resolveDays(): int
    {
        $raw = $this->option('days');

        if ($raw !== null && $raw !== '') {
            $days = (int) $raw;

            if ($days < 1) {
                $this->error('--days 必须大于等于 1');

                exit(self::FAILURE);
            }

            return $days;
        }

        return max(1, (int) config('uploads.prune_days'));
    }
}
