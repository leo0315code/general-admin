<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 上传校验失败（扩展名 / MIME / 体积 / 写入异常）
 *
 * 与系统异常分开的意义：这类失败是「用户操作问题」，
 * 控制器捕获后只把 $userMessage 回显给用户，不暴露磁盘路径等内部细节。
 */
class UploadException extends RuntimeException
{
    public function __construct(
        private readonly string $userMessage,
        string $internalMessage = '',
    ) {
        parent::__construct($internalMessage !== '' ? $internalMessage : $userMessage);
    }

    /** 可直接展示给用户的原因说明 */
    public function userMessage(): string
    {
        return $this->userMessage;
    }
}
