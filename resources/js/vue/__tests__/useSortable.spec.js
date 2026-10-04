import { describe, expect, it } from 'vitest';
import { useSortable } from '../composables/useSortable.js';

describe('useSortable', () => {
    const source = {
        sort: 'id',
        sortDir: 'desc',
        currentUrl: 'https://example.test/console/users',
        query: { search: '张', status: '1', sort: 'id', sort_dir: 'desc', page: '3', empty: '' },
    };

    it('保留非排序类筛选条件，并重置到第 1 页', () => {
        const { sortUrl } = useSortable(source);
        const url = new URL(sortUrl('name'));

        expect(url.searchParams.get('search')).toBe('张');
        expect(url.searchParams.get('status')).toBe('1');
        expect(url.searchParams.get('page')).toBe('1');
        // 空值不带上
        expect(url.searchParams.has('empty')).toBe(false);
        // 原有 sort/sort_dir 被覆盖
        expect(url.searchParams.get('sort')).toBe('name');
    });

    it('点同一列在 asc / desc 间切换', () => {
        const { sortUrl } = useSortable(source);

        // 当前 id/desc → 再点 id 变 asc
        expect(new URL(sortUrl('id')).searchParams.get('sort_dir')).toBe('asc');
        // 换列则从头开始 asc
        expect(new URL(sortUrl('name')).searchParams.get('sort_dir')).toBe('asc');
    });

    it('点同一列且已是 asc 时切回 desc', () => {
        const { sortUrl } = useSortable({ ...source, sort: 'id', sortDir: 'asc' });

        expect(new URL(sortUrl('id')).searchParams.get('sort_dir')).toBe('desc');
    });

    it('sortIcon 反映当前列与方向', () => {
        const { sortIcon } = useSortable(source);

        expect(sortIcon('id')).toBe('heroicon-o-chevron-down');
        expect(sortIcon('name')).toBe('heroicon-o-chevron-up-down');

        const asc = useSortable({ ...source, sort: 'name', sortDir: 'asc' });
        expect(asc.sortIcon('name')).toBe('heroicon-o-chevron-up');
    });

    it('缺少 query / currentUrl 时不报错', () => {
        const { sortUrl, sortIcon } = useSortable({ sort: 'id', sortDir: 'desc' });

        expect(sortUrl('id')).toBe('?sort=id&sort_dir=asc&page=1');
        expect(sortIcon('id')).toBe('heroicon-o-chevron-down');
    });
});
