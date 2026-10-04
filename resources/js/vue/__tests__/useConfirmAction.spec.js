import { beforeEach, describe, expect, it } from 'vitest';
import { useConfirmAction } from '../composables/useConfirmAction.js';

/**
 * 不切 happy-dom：项目 vitest 配置默认 node 环境，实测 happy-dom 仅环境初始化就要 ~30s，
 * 会把 120ms 的套件拖到 33s。这里用最小 DOM 桩，只覆盖我们要验的逻辑
 * （字段组装 / _method 补全 / 数组展开 / 事件载荷 / 赋值方式），不验浏览器行为。
 */
function installDom() {
    const makeEl = (tag) => {
        const el = {
            tag,
            children: [],
            submitted: false,
            submit() {
                this.submitted = true;
            },
            appendChild(child) {
                this.children.push(child);

                return child;
            },
        };

        // 对齐真实 DOM：input.value 赋值会强制转成字符串（null/undefined → ''）
        let raw = '';
        Object.defineProperty(el, 'value', {
            enumerable: true,
            get: () => raw,
            set: (v) => {
                raw = v === null || v === undefined ? '' : String(v);
            },
        });

        return el;
    };

    const state = { csrf: 'test-token', events: [] };
    const document = {
        body: makeEl('body'),
        createElement: makeEl,
        querySelector: (sel) =>
            sel === 'meta[name=csrf-token]' && state.csrf !== null ? { content: state.csrf } : null,
    };

    if (typeof globalThis.CustomEvent === 'undefined') {
        globalThis.CustomEvent = class {
            constructor(type, options = {}) {
                this.type = type;
                this.detail = options.detail;
            }
        };
    }

    globalThis.document = document;
    globalThis.window = {
        dispatchEvent(e) {
            state.events.push(e);
        },
    };

    return state;
}

const fieldsOf = (form) => form.children.map((el) => [el.name, el.value]);

describe('useConfirmAction', () => {
    let state;

    beforeEach(() => {
        state = installDom();
    });

    it('POST 只带 _token，不补 _method', () => {
        const { buildHiddenForm } = useConfirmAction();
        const form = buildHiddenForm({ action: '/console/posts/1' });

        expect(form.method).toBe('POST');
        expect(form.action).toBe('/console/posts/1');
        expect(fieldsOf(form)).toEqual([['_token', 'test-token']]);
    });

    it('非 POST 补 _method 隐藏字段', () => {
        const { buildHiddenForm } = useConfirmAction();
        const form = buildHiddenForm({ action: '/console/users/1', method: 'DELETE' });

        expect(fieldsOf(form)).toEqual([
            ['_token', 'test-token'],
            ['_method', 'DELETE'],
        ]);
    });

    it('数组字段展开为 name[]，null/undefined 跳过', () => {
        const { buildHiddenForm } = useConfirmAction();
        const form = buildHiddenForm({
            action: '/console/users/bulk-delete',
            fields: { ids: [1, 2], name: null, redirect_to: '/console/users' },
        });

        expect(fieldsOf(form)).toEqual([
            ['_token', 'test-token'],
            ['ids[]', '1'],
            ['ids[]', '2'],
            ['redirect_to', '/console/users'],
        ]);
    });

    it('值用属性赋值而非 HTML 拼接——含引号也不会变成额外属性', () => {
        const { buildHiddenForm } = useConfirmAction();
        const evil = 'x" onload="alert(1)';
        const form = buildHiddenForm({ action: '/x', fields: { redirect_to: evil } });

        const input = form.children.find((el) => el.name === 'redirect_to');
        // 原样保留，且没有长出 onload 之类的属性
        expect(input.value).toBe(evil);
        expect(Object.keys(input)).not.toContain('onload');
    });

    it('confirmAction 挂载表单到 body 并派发 app:confirm', () => {
        const { confirmAction } = useConfirmAction();

        const form = confirmAction({
            action: '/console/users/1',
            method: 'DELETE',
            title: '确定删除吗？',
            message: '删除后可在回收站还原。',
        });

        expect(document.body.children).toContain(form);

        const event = state.events[0];
        expect(event.type).toBe('app:confirm');
        expect(event.detail.title).toBe('确定删除吗？');
        expect(event.detail.message).toBe('删除后可在回收站还原。');
        expect(event.detail.variant).toBe('danger'); // 默认危险色
        expect(event.detail.form).toBe(form);
    });

    it('variant 可覆盖为 primary（启停这类非破坏性操作）', () => {
        const { confirmAction } = useConfirmAction();

        confirmAction({ action: '/x', method: 'PATCH', variant: 'primary' });

        expect(state.events[0].detail.variant).toBe('primary');
    });

    it('csrf 可显式传入，覆盖 meta（表单页自带 csrf prop）', () => {
        const { buildHiddenForm } = useConfirmAction();

        expect(fieldsOf(buildHiddenForm({ action: '/x', csrf: 'from-prop' }))).toEqual([
            ['_token', 'from-prop'],
        ]);
    });

    it('submitHiddenForm 直接提交，不派发确认事件', () => {
        const { submitHiddenForm } = useConfirmAction();

        const form = submitHiddenForm({
            action: '/console/notifications/1/read',
            method: 'PATCH',
        });

        expect(form.submitted).toBe(true);
        expect(document.body.children).toContain(form);
        expect(state.events).toHaveLength(0); // 标记已读这类无需二次确认
    });

    it('没有 csrf meta 时不抛错，token 为空串', () => {
        state.csrf = null;
        const { buildHiddenForm } = useConfirmAction();

        expect(fieldsOf(buildHiddenForm({ action: '/x' }))).toEqual([['_token', '']]);
    });
});
