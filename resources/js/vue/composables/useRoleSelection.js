import { ref } from 'vue';

/**
 * 角色/用户编辑表单的角色多选逻辑（纯函数，可单测）
 *
 * - selectedRoles：已选角色 id（字符串数组）
 * - toggleRole：勾选/取消（自动去重，保持插入顺序）
 */
export function useRoleSelection(initialRoles = []) {
    const selectedRoles = ref(
        initialRoles.length > 0 ? initialRoles.map(String) : []
    );

    function toggleRole(id) {
        const s = String(id);
        const i = selectedRoles.value.indexOf(s);

        if (i >= 0) {
            selectedRoles.value.splice(i, 1);
        } else {
            selectedRoles.value.push(s);
        }
    }

    return { selectedRoles, toggleRole };
}
