import { describe, it, expect } from 'vitest';
import { useRoleSelection } from '../composables/useRoleSelection.js';

describe('useRoleSelection 角色多选', () => {
    it('初始为空', () => {
        const { selectedRoles } = useRoleSelection([]);
        expect(selectedRoles.value).toEqual([]);
    });

    it('接收初始角色 id（数字转字符串）', () => {
        const { selectedRoles } = useRoleSelection([1, 2]);
        expect(selectedRoles.value).toEqual(['1', '2']);
    });

    it('toggleRole 勾选/取消', () => {
        const { selectedRoles, toggleRole } = useRoleSelection([]);
        toggleRole(1);
        expect(selectedRoles.value).toEqual(['1']);
        toggleRole(1);
        expect(selectedRoles.value).toEqual([]);
    });

    it('多个角色保持插入顺序且不重复', () => {
        const { selectedRoles, toggleRole } = useRoleSelection([]);
        toggleRole(3);
        toggleRole(1);
        expect(selectedRoles.value).toEqual(['3', '1']);
        toggleRole(3); // 重复点击取消 → 移除 3，保留 1
        expect(selectedRoles.value).toEqual(['1']);
    });

    it('没有初始值时默认空数组', () => {
        const { selectedRoles } = useRoleSelection();
        expect(selectedRoles.value).toEqual([]);
    });
});
