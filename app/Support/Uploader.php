<?php

namespace App\Support;

use App\Exceptions\UploadException;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * 附件上传器（上传基座的唯一直达入口）
 *
 * 安全基线（四条，缺一不可）：
 * 1. **扩展名 + 真实 MIME 双重白名单**：扩展名可伪造，单一校验会被绕过；
 * 2. **随机文件名**：存储名与用户提供的文件名完全无关，杜绝目录穿越/覆盖/脚本名注入；
 * 3. **私有磁盘**：文件落在 storage/app/private，URL 无法直达，也就无法被执行；
 * 4. **体积上限**：应用层再拦一次，不依赖 php.ini。
 *
 * 业务模块复用示例：
 *   $attachment = Uploader::store($request->file('file'), $request->user());
 */
class Uploader
{
    /**
     * 保存上传文件并落库
     *
     * @throws UploadException 校验失败或写入失败
     */
    public static function store(UploadedFile $file, User $user): Attachment
    {
        self::assertValid($file);
        self::assertWithinQuota($file, $user);

        $disk = (string) config('uploads.disk');
        $extension = self::extensionOf($file);
        $path = self::buildPath($extension);
        $checksum = self::checksumOf($file);

        try {
            $written = Storage::disk($disk)->put($path, fopen($file->getRealPath(), 'rb'));
        } catch (Throwable $e) {
            Log::error('附件写入磁盘失败', ['error' => $e->getMessage()]);
            throw new UploadException('文件上传失败，请稍后重试。', $e->getMessage());
        }

        if (! $written) {
            throw new UploadException('文件上传失败，请稍后重试。', "磁盘 {$disk} 写入返回 false：{$path}");
        }

        // 写库失败要清掉已落盘的文件，避免留下无主文件
        try {
            return Attachment::query()->create([
                'user_id' => $user->getKey(),
                'disk' => $disk,
                'path' => $path,
                'name' => self::safeOriginalName($file),
                'extension' => $extension,
                'mime' => (string) $file->getMimeType(),
                'size' => $file->getSize(),
                'checksum' => $checksum,
            ]);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);
            Log::error('附件记录落库失败，已清理文件', ['path' => $path, 'error' => $e->getMessage()]);

            throw new UploadException('文件保存失败，请稍后重试。', $e->getMessage());
        }
    }

    /** 删除附件（文件 + 记录）；文件不存在也视为成功 */
    public static function delete(Attachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    /** 可读体积，如 1.2 MB */
    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;

        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? (int) $value : number_format($value, 1)).' '.$unit;
            }
            $value /= 1024;
        }

        return number_format($value, 1).' GB';
    }

    /** 校验：上传状态 / 体积 / 扩展名 / MIME */
    private static function assertValid(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new UploadException('文件上传未完成，请重试。', 'UploadedFile 无效：'.$file->getError());
        }

        $maxBytes = (int) config('uploads.max_size') * 1024;

        if ($maxBytes > 0 && $file->getSize() > $maxBytes) {
            throw new UploadException(
                '文件超过大小上限（'.self::humanSize($maxBytes).'）。',
                "体积 {$file->getSize()} > {$maxBytes}"
            );
        }

        $extension = self::extensionOf($file);

        if (! in_array($extension, (array) config('uploads.extensions'), true)) {
            throw new UploadException("不支持的文件类型（.{$extension}）。", "扩展名不在白名单：{$extension}");
        }

        $mime = strtolower((string) $file->getMimeType());

        if (! in_array($mime, (array) config('uploads.mimes'), true)) {
            throw new UploadException('文件内容类型不在允许范围内。', "MIME 不在白名单：{$mime}");
        }
    }

    /** 用户配额：已用 + 本次 超过上限即拒绝（0 表示不限） */
    private static function assertWithinQuota(UploadedFile $file, User $user): void
    {
        $quotaMb = (int) config('uploads.user_quota');

        if ($quotaMb <= 0) {
            return;
        }

        $quotaBytes = $quotaMb * 1024 * 1024;
        $used = (int) Attachment::query()->where('user_id', $user->getKey())->sum('size');

        if ($used + $file->getSize() > $quotaBytes) {
            throw new UploadException(
                '你的附件空间已用完（上限 '.self::humanSize($quotaBytes).'，已用 '.self::humanSize($used).'）。',
                "用户 {$user->getKey()} 配额超限：已用 {$used} + 本次 {$file->getSize()} > {$quotaBytes}"
            );
        }
    }

    /** 原始文件名：仅用于下载展示，去掉路径并限长，绝不用于磁盘存储 */
    private static function safeOriginalName(UploadedFile $file): string
    {
        $name = basename((string) $file->getClientOriginalName());
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?: 'file';

        return mb_substr($name, 0, 255);
    }

    private static function extensionOf(UploadedFile $file): string
    {
        return strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
    }

    /** 按 前缀/年/月/随机名.扩展名 组织路径 */
    private static function buildPath(string $extension): string
    {
        $prefix = trim((string) config('uploads.prefix'), '/');
        $length = (int) config('uploads.name_length');

        return sprintf(
            '%s/%s/%s/%s.%s',
            $prefix,
            now()->format('Y'),
            now()->format('m'),
            Str::random($length > 0 ? $length : 40),
            $extension
        );
    }

    private static function checksumOf(UploadedFile $file): ?string
    {
        $real = $file->getRealPath();

        return ($real && is_file($real)) ? (hash_file('sha256', $real) ?: null) : null;
    }
}
