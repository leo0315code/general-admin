import { describe, it, expect } from 'vitest';
import { usePermissionSelection } from '../composables/usePermissionSelection.js';

/** 权限树扁平行（模拟 Menu::flatten 输出） */
const rows = [
    { id: 1, title: '仪表盘', permission_id: 101, group_ids: [], depth: 0 },
    { id: 2, title: '用户管理', permission_id: 102, group_ids: [102, 103, 104], depth: 0 },
    { id: 3, title: '新增用户', permission_id: 103, group_ids: [], depth: 1 },
    { id: 4, title: '删除用户', permission_id: 104, group_ids: [], depth: 1 },
    // 目录节点：无 permission_id，不可勾选
    { id: 5, title: '仅目录', permission_id: null, group_ids: [], depth: 0 },
];

describe('usePermissionSelection 权限树选择', () => {
    it('初始为空，无任何勾选', () => {
        const { selected, isChecked } = usePermissionSelection([], rows);
        expect(selected.value).toEqual([]);
        expect(isChecked(101)).toBe(false);
    });

    it('接收初始权限数组（数字/字符串兼容）', () => {
        const { selected, isChecked } = usePermissionSelection([101, '103'], rows);
        expect(selected.value).toEqual(['101', '103']);
        expect(isChecked(101)).toBe(true);
        expect(isChecked(103)).toBe(true);
        expect(isChecked(102)).toBe(false);
    });

    it('togglePermission 单个切换', () => {
        const { selected, togglePermission, isChecked } = usePermissionSelection([], rows);
        togglePermission(101);
        expect(selected.value).toEqual(['101']);
        togglePermission(101);
        expect(selected.value).toEqual([]);
        // null 权限节点不生效
        togglePermission(null);
        expect(selected.value).toEqual([]);
    });

    it('toggleAll(true) 全选所有可勾选权限，目录节点排除', () => {
        const { selected, toggleAll } = usePermissionSelection([], rows);
        toggleAll(true);
        expect(selected.value.sort()).toEqual(['101', '102', '103', '104']);
    });

    it('toggleAll(false) 取消全部（保留不可勾选节点无影响）', () => {
        const { selected, toggleAll } = usePermissionSelection(['101', '102', '103'], rows);
        toggleAll(false);
        expect(selected.value).toEqual([]);
    });

    it('toggleGroup 全选本组（含组内全部权限）', () => {
        const { selected, toggleGroup, groupChecked } = usePermissionSelection([], rows);
        const userGroup = rows.find((r) => r.id === 2);

        toggleGroup(userGroup);
        expect(selected.value.sort()).toEqual(['102', '103', '104']);
        expect(groupChecked(userGroup)).toBe(true);

        // 再次点击 → 整组取消
        toggleGroup(userGroup);
        expect(selected.value).toEqual([]);
        expect(groupChecked(userGroup)).toBe(false);
    });

    it('toggleGroup 不影响组外已选权限', () => {
        const { selected, toggleGroup } = usePermissionSelection(['101'], rows);
        toggleGroup(rows.find((r) => r.id === 2));
        expect(selected.value.sort()).toEqual(['101', '102', '103', '104']);
        // 取消本组后保留组外权限
        toggleGroup(rows.find((r) => r.id === 2));
        expect(selected.value).toEqual(['101']);
    });

    it('groupChecked：组内部分勾选时为 false', () => {
        const { groupChecked } = usePermissionSelection(['102'], rows);
        expect(groupChecked(rows.find((r) => r.id === 2))).toBe(false);
    });

    it('group_ids 为空的节点 groupChecked 恒为 false', () => {
        const { groupChecked } = usePermissionSelection(['101'], rows);
        expect(groupChecked(rows.find((r) => r.id === 1))).toBe(false);
    });
});
