/**
 * 服务端排序表头（GET 整页刷新式）
 *
 * 背景：列表页的排序链接走 Blade 分页 + GET 跳转（不是前端排序），
 * `sortUrl` / `sortIcon` 这两个函数在 8 个列表页里逐字符重复：
 * DictItemsIndex / DictTypesIndex / LogsIndex / PostsIndex / PostsTrash /
 * RolesIndex / UsersIndex / UsersTrash。
 *
 * 约定 props（8 个页面命名一致）：
 *   sort       当前排序字段
 *   sortDir    当前方向 asc|desc
 *   currentUrl 列表页基础 URL（不含 query）
 *   query      当前 query 参数对象（request()->query()）
 *
 * 用法：
 *   const { sortUrl, sortIcon } = useSortable(props);
 *   <a :href="sortUrl(col.key)">{{ col.label }}</a>
 *   <Icon :name="sortIcon(col.key)" />
 *
 * @param {{ sort?: string, sortDir?: string, currentUrl?: string, query?: Object }} source
 *        传 props（响应式代理）或普通对象均可
 * @returns {{ sortUrl: Function, sortIcon: Function }}
 */
export function useSortable(source = {}) {
    /**
     * 生成排序链接：保留除 sort/sort_dir/page 外的现有筛选条件，并重置到第 1 页
     *
     * @param {string} key 排序字段名
     * @returns {string}
     */
    function sortUrl(key) {
        const q = new URLSearchParams();

        for (const [k, v] of Object.entries(source.query || {})) {
            if (k === 'sort' || k === 'sort_dir' || k === 'page') continue;
            if (v === undefined || v === null || v === '') continue;

            q.append(k, v);
        }

        q.set('sort', key);
        q.set('sort_dir', source.sort === key && source.sortDir === 'asc' ? 'desc' : 'asc');
        q.set('page', '1');

        return `${source.currentUrl || ''}?${q.toString()}`;
    }

    /**
     * 排序图标：当前列按方向显示上/下箭头，其余列显示可排序提示
     *
     * @param {string} key 排序字段名
     * @returns {string} heroicon 名称
     */
    function sortIcon(key) {
        if (source.sort === key) {
            return source.sortDir === 'asc'
                ? 'heroicon-o-chevron-up'
                : 'heroicon-o-chevron-down';
        }

        return 'heroicon-o-chevron-up-down';
    }

    return { sortUrl, sortIcon };
}
