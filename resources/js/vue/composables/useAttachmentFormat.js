/**
 * 附件上传相关纯函数（与 DOM / XHR 无关，便于单元测试）
 */

/** 字节数 → 人类可读大小（与旧 sizeText 行为一致） */
export function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return '';
    const kb = bytes / 1024;

    return kb >= 1024 ? `${(kb / 1024).toFixed(2)} MB` : `${Math.max(1, Math.round(kb))} KB`;
}

/** 是否图片文件（用于决定要不要生成缩略图预览） */
export function isImageFile(file) {
    return !!file && typeof file.type === 'string' && file.type.startsWith('image/');
}
