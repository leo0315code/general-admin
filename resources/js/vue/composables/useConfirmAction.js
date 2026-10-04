/**
 * 确认类操作：构造隐藏表单 → 派发 app:confirm → AppShell 的 ConfirmModal 接管
 *
 * 背景：此前「建隐藏表单 + 提交/派发 app:confirm」这段逻辑在全项目抄了 17 份：
 *   - 8 个列表页：UsersIndex / PostsIndex / RolesIndex / MenusIndex /
 *                 DictItemsIndex / DictTypesIndex / PostsTrash / UsersTrash
 *   - 5 个表单页：UserForm? 之外是 PostForm / RoleForm / MenuForm /
 *                 DictItemForm / DictTypeForm
 *   - 4 个其它页：AttachmentsIndex / MessagesIndex / NotificationsIndex / ProfileForm
 * 且写法分裂：多数用 `form.innerHTML = \`<input value="${val}">\``（值未转义，
 * ProfileForm 甚至只手工替换了一个双引号字符），少数用 DOM API。
 * 统一走 DOM API，既收敛重复也消掉字符串拼接的注入面。
 *
 * 用法：
 *   const { confirmAction } = useConfirmAction();
 *   confirmAction({
 *       action: '/console/users/1',
 *       method: 'DELETE',                     // 非 POST 时自动补 _method
 *       fields: { ids: [1, 2], redirect_to: '/x' },  // 数组自动展开为 name[]
 *       title: '确定删除吗？',
 *       message: '删除后可在回收站还原。',
 *       variant: 'danger',                    // danger | primary，ConfirmModal 的按钮配色
 *   });
 *
 * @returns {{ confirmAction: Function, submitHiddenForm: Function, buildHiddenForm: Function }}
 */
export function useConfirmAction() {
    /**
     * 组装隐藏字段表单（不挂载、不派发事件，便于单测与复用）
     *
     * @param {{ action: string, method?: string, fields?: Object, csrf?: string }} options
     *        csrf 省略时读 <meta name="csrf-token">；表单页通常自带 csrf prop，可显式传入
     * @returns {HTMLFormElement}
     */
    function buildHiddenForm({ action, method = 'POST', fields = {}, csrf = null }) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;

        appendField(form, '_token', csrf ?? csrfToken());

        if (method && method !== 'POST') {
            appendField(form, '_method', method);
        }

        for (const [name, value] of Object.entries(fields || {})) {
            appendField(form, name, value);
        }

        return form;
    }

    /**
     * 构造表单并请求全局确认弹窗
     *
     * @param {{ action: string, method?: string, fields?: Object, csrf?: string, title?: string, message?: string, variant?: string }} options
     * @returns {HTMLFormElement} 已挂载到 body 的表单（ConfirmModal 确认后 submit）
     */
    function confirmAction({
        action,
        method = 'POST',
        fields = {},
        csrf = null,
        title = '',
        message = '',
        variant = 'danger',
    }) {
        const form = buildHiddenForm({ action, method, fields, csrf });

        document.body.appendChild(form);
        window.dispatchEvent(
            new CustomEvent('app:confirm', { detail: { form, title, message, variant } })
        );

        return form;
    }

    /**
     * 直接提交隐藏表单（不经确认弹窗）
     *
     * 用于本身已有确认交互的场景：如通知「标记已读」无需二次确认，
     * 个人资料「注销账号」已在自己的密码确认弹窗里核过密码。
     *
     * @param {{ action: string, method?: string, fields?: Object, csrf?: string }} options
     * @returns {HTMLFormElement}
     */
    function submitHiddenForm(options) {
        const form = buildHiddenForm(options);

        document.body.appendChild(form);
        form.submit();

        return form;
    }

    return { confirmAction, submitHiddenForm, buildHiddenForm };
}

/** CSRF token（Blade 布局 <meta name="csrf-token">） */
function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

/**
 * 追加隐藏字段：数组展开为 `name[]` 多个字段，null/undefined 跳过
 *
 * 一律用 DOM API 赋值（el.value =），不拼 HTML 字符串——值可能来自用户输入或 URL。
 */
function appendField(form, name, value) {
    if (value === null || value === undefined) {
        return;
    }

    if (Array.isArray(value)) {
        value.forEach((item) => appendField(form, `${name}[]`, item));

        return;
    }

    const el = document.createElement('input');
    el.type = 'hidden';
    el.name = name;
    el.value = value;
    form.appendChild(el);
}
