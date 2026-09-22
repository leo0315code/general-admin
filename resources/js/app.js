import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * 全局事件桥 → Vue AppShell（Vue3 渐进式迁移）
 * ------------------------------------------------------------
 * Alpine 的 toast / confirmModal store 改为转发 window 事件，
 * 由 Vue AppShell 渲染的 Toast / ConfirmModal 实际展示。
 * 现有 Blade 页面调用语法不变，交互内核已切 Vue。
 * 事件缓冲：Vue 挂载前（Alpine 初始化期间）触发的事件先入队，
 * AppShell 挂载后消费，避免 flash toast 等丢失。
 */
window.__appEvents = [];

function emitAppEvent(name, detail = {}) {
    window.__appEvents.push({ name, detail, time: Date.now() });
    window.dispatchEvent(new CustomEvent(name, { detail }));
}

/**
 * 全局 Toast（桥接 Vue Toast）
 *
 * 用法：Alpine.store('toast').show('success', '操作成功')
 *       Alpine.store('toast').success('...') / .error('...')
 */
Alpine.store('toast', {
    show(type, message, duration = 3000) {
        emitAppEvent('app:toast', { type, message, duration });
    },

    success(message) {
        this.show('success', message);
    },

    error(message) {
        this.show('error', message);
    },
});

/**
 * 全局确认弹窗 store（桥接 Vue ConfirmModal）
 *
 * 触发按钮：
 *   @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))"
 * 表单需带 data-confirm-title / data-confirm-message（可选 data-confirm-variant="primary|danger"）。
 * 确认后由 Vue ConfirmModal 执行 form.submit()（真实表单，CSRF/Gate 不变）
 */
Alpine.store('confirmModal', {
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

    submit() {
        // 兼容旧模板引用：确认由 Vue ConfirmModal 处理 form.submit()
    },
});

/**
 * 列表勾选辅助（DataTable 全选 + 行勾选 + BulkActions）
 *
 * 用法：列表卡片容器加 x-data="listSelection()"，
 * DataTable 表头复选框 @change="toggleAll($event)" / :checked="selectAll"，
 * 行复选框 value=行ID + x-model="selectedIds" + @change="syncSelectAll"。
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('listSelection', () => ({
        selectedIds: [],
        selectAll: false,

        toggleAll(event) {
            const boxes = [...document.querySelectorAll('[data-select-row]')];

            if (event.target.checked) {
                this.selectedIds = boxes.map((el) => el.value);
                this.selectAll = true;
            } else {
                this.selectedIds = [];
                this.selectAll = false;
            }
        },

        syncSelectAll() {
            const total = document.querySelectorAll('[data-select-row]').length;
            this.selectAll = total > 0 && this.selectedIds.length === total;
        },
    }));
});

/**
 * 侧边栏目录折叠（localStorage 记忆，刷新后保持）
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('sidebarGroups', () => ({
        collapsed: JSON.parse(localStorage.getItem('sidebar.collapsed') || '[]'),

        isCollapsed(title) {
            return this.collapsed.includes(title);
        },

        toggle(title) {
            const i = this.collapsed.indexOf(title);

            if (i >= 0) {
                this.collapsed.splice(i, 1);
            } else {
                this.collapsed.push(title);
            }

            localStorage.setItem('sidebar.collapsed', JSON.stringify(this.collapsed));
        },
    }));
});

/**
 * 顶栏全局搜索（客户端过滤当前用户可见菜单）
 * 数据由 layouts/app.blade.php 注入（$navGroups，来自 Navigation::forUser）
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('globalSearch', () => ({
        open: false,
        query: '',
        navGroups: window.__navGroups ?? [],

        get results() {
            const q = this.query.trim().toLowerCase();

            if (q === '') {
                return [];
            }

            return this.navGroups
                .flatMap((group) => group.items.filter((item) => item.url))
                .filter((item) => item.title.toLowerCase().includes(q) || item.url.toLowerCase().includes(q))
                .slice(0, 8);
        },

        focusInput() {
            this.$nextTick(() => this.$refs.input?.focus());
        },

        go(url) {
            this.open = false;
            this.query = '';
            window.location.href = url;
        },
    }));
});

Alpine.start();

/**
 * Vue 3 渐进式挂载（组件化迁移）：
 * Blade 端 <div data-vue-app data-component="xxx" :data-props='@json(...)'>
 * 由 mountVueApps() 扫描挂载。迁移期与 Alpine 共存，Vue 挂载根请加 x-ignore。
 */
import { mountVueApps } from './vue/bootstrap';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountVueApps);
} else {
    mountVueApps();
}
