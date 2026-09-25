import { ref } from 'vue';

/**
 * 角色权限树选择逻辑（纯函数，可单测）
 *
 * - selected：已选权限 id（字符串数组）
 * - togglePermission：切换单个权限
 * - toggleGroup：全选本组（group_ids 批量勾选/取消，组内全勾则取消）
 * - groupChecked：本组是否全部勾选
 * - toggleAll：全选 / 取消全选（仅针对有 permission_id 的节点）
 * - isChecked：单个权限是否已选
 *
 * @param {Array<string|number>} initialPermissions 初始已选权限 id
 * @param {Array<{permission_id: number|null, group_ids: number[]}>} rows 权限树扁平行
 */
export function usePermissionSelection(initialPermissions = [], rows = []) {
    const selected = ref(initialPermissions.map(String));

    const checkableIds = () => rows
        .map((r) => r.permission_id)
        .filter((pid) => pid !== null && pid !== undefined)
        .map(String);

    function togglePermission(pid) {
        if (pid === null || pid === undefined) return;

        const s = String(pid);
        const i = selected.value.indexOf(s);

        if (i >= 0) {
            selected.value.splice(i, 1);
        } else {
            selected.value.push(s);
        }
    }

    function toggleGroup(row) {
        const ids = (row.group_ids || []).map(String);

        if (ids.length === 0) return;

        const allChecked = ids.every((id) => selected.value.includes(id));

        if (allChecked) {
            selected.value = selected.value.filter((s) => !ids.includes(s));
        } else {
            ids.forEach((id) => {
                if (!selected.value.includes(id)) selected.value.push(id);
            });
        }
    }

    function groupChecked(row) {
        const ids = (row.group_ids || []).map(String);

        return ids.length > 0 && ids.every((id) => selected.value.includes(id));
    }

    function toggleAll(source) {
        const checkable = checkableIds();

        if (source) {
            checkable.forEach((id) => {
                if (!selected.value.includes(id)) selected.value.push(id);
            });
        } else {
            selected.value = selected.value.filter((s) => !checkable.includes(s));
        }
    }

    function isChecked(pid) {
        return pid !== null && pid !== undefined && selected.value.includes(String(pid));
    }

    return { selected, togglePermission, toggleGroup, groupChecked, toggleAll, isChecked };
}
