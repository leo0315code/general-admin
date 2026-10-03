<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 上传存储磁盘
    |--------------------------------------------------------------------------
    |
    | 默认 local（storage/app/private，私有磁盘）：文件无法通过 URL 直接访问，
    | 必须走带鉴权的下载路由。若确需公开直链（如图片 CDN），可改为 public，
    | 但务必确认白名单中不含任何可被服务端执行/浏览器解析为脚本的类型。
    |
    */
    'disk' => env('UPLOAD_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | 单文件体积上限（KB）
    |--------------------------------------------------------------------------
    |
    | 应用层上限，与 php.ini 的 upload_max_filesize / post_max_size 取小者生效。
    | 默认 10MB；放宽时请同步确认 php.ini 与 Nginx client_max_body_size。
    |
    */
    'max_size' => (int) env('UPLOAD_MAX_SIZE', 10240),

    /*
    |--------------------------------------------------------------------------
    | 扩展名白名单（小写）
    |--------------------------------------------------------------------------
    |
    | 刻意不含 svg / html / htm / php / js：
    | - svg 可内嵌脚本，浏览器打开即 XSS；
    | - html/php/js 一旦能被引擎解析就是 RCE。
    |
    */
    'extensions' => [
        // 图片
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico',
        // 文档
        'pdf', 'txt', 'csv', 'md',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        // 压缩包
        'zip', 'rar', '7z', 'gz', 'tar',
    ],

    /*
    |--------------------------------------------------------------------------
    | MIME 白名单（真实类型，非客户端声明）
    |--------------------------------------------------------------------------
    |
    | 扩展名可以伪造，MIME 由服务端检测，两者都必须在白名单内才放行。
    | 常见环境差异（如 csv 被识别为 text/plain）已一并收录。
    |
    */
    'mimes' => [
        // 图片
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'image/x-icon', 'image/vnd.microsoft.icon',
        // 文档
        'application/pdf',
        'text/plain', 'text/csv', 'text/markdown', 'text/x-markdown',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // 压缩包
        'application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed',
        'application/x-7z-compressed', 'application/gzip', 'application/x-tar',
        // 兜底：部分环境对未知二进制返回 octet-stream
        'application/octet-stream',
    ],

    /*
    |--------------------------------------------------------------------------
    | 存储目录前缀与命名
    |--------------------------------------------------------------------------
    |
    | 路径按 年/月 分片避免单目录文件过多；文件名随机 40 位，
    | 既防止覆盖他人文件，也杜绝用文件名做目录穿越 / 注入。
    |
    */
    'prefix' => 'attachments',
    'name_length' => 40,

    /*
    |--------------------------------------------------------------------------
    | 配额与留存
    |--------------------------------------------------------------------------
    |
    | user_quota：单个用户已用空间上限（MB），0 表示不限制。
    |   上传前会累加该用户现有附件体积，超额直接拒绝，避免磁盘被写满。
    | prune_days：清理命令保留天数，早于该天数的附件会被 `attachments:prune` 清掉。
    |
    */
    'user_quota' => (int) env('UPLOAD_USER_QUOTA', 0),
    'prune_days' => (int) env('UPLOAD_PRUNE_DAYS', 90),
];
