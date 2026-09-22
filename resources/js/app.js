/**
 * 通用管理后台 · 前端入口（原生 JS + Vue3 渐进式）
 * ------------------------------------------------------------
 * Alpine 已移除：布局交互（暗色/侧边栏/分组折叠/全局搜索/Flash/提交按钮）
 * 全部改为原生 JS（window.Layout / window.__ui），Vue 只负责交互组件与页面。
 */

/**
 * 全局事件桥 → Vue AppShell（Toast / ConfirmModal 实际渲染）
 * 事件缓冲：Vue 挂载前触发的事件先入队，AppShell 挂载后消费。
 */
window.__appEvents = [];

function emitAppEvent(name, detail = {}) {
    window.__appEvents.push({ name, detail, time: Date.now() });
    window.dispatchEvent(new CustomEvent(name, { detail }));
}

/**
 * 全局 UI 工具对象（替代原 Alpine.store）
 * 用法：window.__ui.toast.success('...') / window.__ui.confirmModal.open(form)
 */
window.__ui = {
    toast: {
        show(type, message, duration = 3000) {
            emitAppEvent('app:toast', { type: type === 'error' ? 'error' : 'success', message, duration });
        },
        success(message) {
            this.show('success', message);
        },
        error(message) {
            this.show('error', message);
        },
    },

    confirmModal: {
        open(form) {
            emitAppEvent('app:confirm', {
                form,
                title: form?.dataset.confirmTitle || '确定继续吗？',
                message: form?.dataset.confirmMessage || '',
                confirmText: form?.dataset.confirmText || '确认',
                cancelText: form?.dataset.cancelText || '取消',
                variant: form?.dataset.confirmVariant || 'danger',
            });
        },
        close() {
            emitAppEvent('app:confirm-close', {});
        },
    },
};

/**
 * 布局交互（原生 JS，替代 Alpine 布局指令）
 */
window.Layout = {
    dark: false,
    sidebarOpen: false,

    init() {
        this.dark = document.documentElement.classList.contains('dark');
        this.initDark();
        this.initSidebar();
        this.initSidebarGroups();
        this.initSearch();
        this.initFlash();
        this.initSubmitButtons();
        this.initModal();
        this.initDropdowns();
    },

    /* ---- 明暗切换 ---- */
    initDark() {
        document.querySelectorAll('[data-action="toggle-dark"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                this.dark = !this.dark;
                document.documentElement.classList.toggle('dark', this.dark);
                localStorage.setItem('theme', this.dark ? 'dark' : 'light');
                this.syncDarkIcons();
            });
        });
        this.syncDarkIcons();
    },

    syncDarkIcons() {
        document.querySelectorAll('[data-action="toggle-dark"]').forEach((btn) => {
            btn.querySelector('[data-icon-sun]')?.classList.toggle('hidden', !this.dark);
            btn.querySelector('[data-icon-moon]')?.classList.toggle('hidden', this.dark);
        });
    },

    /* ---- 移动端侧边栏 ---- */
    initSidebar() {
        const sidebar = document.getElementById('app-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!sidebar) return;

        const apply = () => {
            sidebar.classList.toggle('translate-x-0', this.sidebarOpen);
            sidebar.classList.toggle('-translate-x-full', !this.sidebarOpen);
            overlay?.classList.toggle('hidden', !this.sidebarOpen);
        };

        document.querySelectorAll('[data-action="toggle-sidebar"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                this.sidebarOpen = !this.sidebarOpen;
                apply();
            });
        });
        overlay?.addEventListener('click', () => {
            this.sidebarOpen = false;
            apply();
        });
    },

    /* ---- 侧边栏目录折叠（localStorage 记忆） ---- */
    initSidebarGroups() {
        let collapsed = [];
        try {
            collapsed = JSON.parse(localStorage.getItem('sidebar.collapsed') || '[]');
        } catch (e) {
            collapsed = [];
        }

        document.querySelectorAll('[data-collapse-group]').forEach((btn) => {
            const title = btn.dataset.collapseGroup;
            const body = btn.closest('.sidebar-group')?.querySelector('.sidebar-group-body');
            const arrow = btn.querySelector('.sidebar-arrow');
            if (!body) return;

            const apply = () => {
                const isCollapsed = collapsed.includes(title);
                body.classList.toggle('hidden', isCollapsed);
                btn.setAttribute('aria-expanded', String(!isCollapsed));
                arrow?.classList.toggle('-rotate-90', isCollapsed);
            };

            apply();
            btn.addEventListener('click', () => {
                const i = collapsed.indexOf(title);
                if (i >= 0) collapsed.splice(i, 1);
                else collapsed.push(title);
                localStorage.setItem('sidebar.collapsed', JSON.stringify(collapsed));
                apply();
            });
        });
    },

    /* ---- 顶栏全局搜索（客户端过滤当前用户可见菜单） ---- */
    initSearch() {
        const input = document.querySelector('[data-search-input]');
        if (!input) return;

        const root = input.closest('[data-search-root]');
        const results = root?.querySelector('[data-search-results]');
        const empty = root?.querySelector('[data-search-empty]');
        const navGroups = window.__navGroups ?? [];

        const filter = (q) =>
            navGroups
                .flatMap((g) => g.items.filter((item) => item.url))
                .filter((item) => item.title.toLowerCase().includes(q) || item.url.toLowerCase().includes(q))
                .slice(0, 8);

        const render = () => {
            const q = input.value.trim().toLowerCase();
            if (!results || !empty) return;

            if (q === '') {
                results.classList.add('hidden');
                empty.classList.add('hidden');
                return;
            }

            const items = filter(q);
            results.innerHTML = items
                .map(
                    (r) =>
                        `<a href="${r.url}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <span class="inline-flex items-center justify-center h-7 w-7 shrink-0 rounded-lg bg-primary-100 dark:bg-primary-500/20 text-primary-600 dark:text-primary-300">
                                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                            </span>
                            <span class="truncate">${r.title}</span>
                        </a>`
                )
                .join('');

            results.classList.toggle('hidden', items.length === 0);
            empty.classList.toggle('hidden', items.length > 0);
        };

        const close = () => {
            results?.classList.add('hidden');
            empty?.classList.add('hidden');
        };

        input.addEventListener('input', render);
        input.addEventListener('focus', render);
        root?.addEventListener('click', (e) => {
            if (e.target.closest('a')) {
                input.value = '';
                close();
            }
        });
        document.addEventListener('click', (e) => {
            if (root && !root.contains(e.target)) close();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
            if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
                e.preventDefault();
                input.focus();
            }
        });
    },

    /* ---- Flash 消息 → Vue Toast ---- */
    initFlash() {
        document.querySelectorAll('[data-flash-type]').forEach((el) => {
            window.__ui.toast.show(el.dataset.flashType, el.dataset.flashMessage || '');
        });
    },

    /* ---- 提交按钮防锁死（必填校验未过不进入 loading） ---- */
    initSubmitButtons() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-submit-button]');
            if (!btn || btn.disabled) return;

            const form = btn.form;
            if (form && !form.checkValidity()) return; // 浏览器展示校验提示，不锁死

            btn.disabled = true;
            btn.classList.add('opacity-70', 'cursor-wait');
            btn.querySelector('[data-loading-spinner]')?.classList.remove('hidden');
            btn.querySelector('[data-loading-label]')?.classList.remove('hidden');
            btn.querySelector('[data-label]')?.classList.add('hidden');
        });
    },

    /* ---- 下拉菜单（原生 JS，替代 Breeze x-dropdown） ---- */
    initDropdowns() {
        const openDropdown = (root, panel) => {
            root.classList.add('dropdown-open');
            panel.classList.remove('hidden');
        };
        const closeDropdown = (root) => {
            root.classList.remove('dropdown-open');
            root.querySelector('[data-dropdown-panel]')?.classList.add('hidden');
        };

        document.addEventListener('click', (e) => {
            const root = e.target.closest('[data-dropdown]');
            document.querySelectorAll('[data-dropdown].dropdown-open').forEach((d) => {
                if (d !== root) closeDropdown(d);
            });

            if (!root) return;

            const panel = root.querySelector('[data-dropdown-panel]');
            const onTrigger = e.target.closest('[data-dropdown-trigger]');
            const inPanel = e.target.closest('[data-dropdown-panel]');

            if (onTrigger) {
                root.classList.contains('dropdown-open') ? closeDropdown(root) : openDropdown(root, panel);
            } else if (!inPanel) {
                closeDropdown(root);
            } else {
                closeDropdown(root); // 面板内点击原逻辑：关闭
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('[data-dropdown].dropdown-open').forEach(closeDropdown);
            }
        });
    },

    initModal() {
        const modals = document.querySelectorAll('[data-modal]');

        const open = (name) => {
            modals.forEach((m) => {
                if (m.dataset.modal === name) {
                    m.classList.remove('hidden');
                    document.body.classList.add('overflow-y-hidden');
                    const focusable = m.querySelector('input, button, a, select, textarea');
                    setTimeout(() => focusable?.focus(), 100);
                }
            });
        };

        const close = (el) => {
            const modal = el.closest('[data-modal]');
            if (modal) {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-y-hidden');
            }
        };

        window.addEventListener('open-modal', (e) => open(e.detail));
        window.addEventListener('close-modal', () => {
            modals.forEach((m) => m.classList.add('hidden'));
            document.body.classList.remove('overflow-y-hidden');
        });
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-modal-close]')) close(e.target);
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                modals.forEach((m) => m.classList.add('hidden'));
                document.body.classList.remove('overflow-y-hidden');
            }
        });
    },
};

/* ---- Vue 3 渐进式挂载 ---- */
import { mountVueApps } from './vue/bootstrap';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.Layout.init();
        mountVueApps();
    });
} else {
    window.Layout.init();
    mountVueApps();
}
