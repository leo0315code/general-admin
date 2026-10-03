import { describe, it, expect } from 'vitest';
import { formatBytes, isImageFile } from '../composables/useAttachmentFormat.js';

describe('formatBytes', () => {
    it('0 字节显示 1 KB（与旧逻辑一致，向上取整）', () => {
        expect(formatBytes(0)).toBe('1 KB');
    });

    it('KB 级别显示 KB 并四舍五入（0.5KB 向上取整为 1）', () => {
        expect(formatBytes(512)).toBe('1 KB');
        expect(formatBytes(524288)).toBe('512 KB');
        expect(formatBytes(1023 * 1024)).toBe('1023 KB');
    });

    it('MB 级别保留两位小数（以 1024KB 为阈值）', () => {
        expect(formatBytes(1536 * 1024)).toBe('1.50 MB');
        expect(formatBytes(10 * 1024 * 1024)).toBe('10.00 MB');
    });

    it('非法输入返回空串', () => {
        expect(formatBytes(-1)).toBe('');
        expect(formatBytes(NaN)).toBe('');
        expect(formatBytes(undefined)).toBe('');
    });
});

describe('isImageFile', () => {
    it('image/* 一律判定为图片', () => {
        expect(isImageFile({ type: 'image/png' })).toBe(true);
        expect(isImageFile({ type: 'image/jpeg' })).toBe(true);
        expect(isImageFile({ type: 'image/webp' })).toBe(true);
    });

    it('非图片类型与空值判定为否', () => {
        expect(isImageFile({ type: 'text/plain' })).toBe(false);
        expect(isImageFile({ type: 'application/pdf' })).toBe(false);
        expect(isImageFile(null)).toBe(false);
        expect(isImageFile(undefined)).toBe(false);
        expect(isImageFile({})).toBe(false);
    });
});
