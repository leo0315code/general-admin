import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * 全局 Toast（轻提示）store —— 右上角堆叠，3s 自动消失
 *
 * 用法：Alpine.store('toast').show('success', '操作成功')
 *       Alpine.store('toast').success('...') / .error('...')
 */
Alpine.store('toast', {
    items: [],
    nextId: 1,

    show(type, message, duration = 3000) {
        const id = this.nextId++;
        this.items.push({ id, type: type === 'error' ? 'error' : 'success', message, visible: false });

        const item = this.items[this.items.length - 1];

        Alpine.nextTick(() => {
            item.visible = true;
        });

        setTimeout(() => {
            item.visible = false;
            setTimeout(() => {
                this.items = this.items.filter((t) => t.id !== id);
            }, 200);
        }, duration);
    },

    success(message) {
        this.show('success', message);
    },

    error(message) {
        this.show('error', message);
    },
});

/**
 * 全局确认弹窗 store（UI 现代化重构 · SEC-1）
 *
 * 触发按钮：
 *   @click.prevent="Alpine.store('confirmModal').open($el.closest('form'))"
 * 表单需带 data-confirm-title / data-confirm-message（可选 data-confirm-variant="primary|danger"）。
 * 确认后：Alpine.store('confirmModal').submit() → form.submit()（真实表单，CSRF/Gate 不变）
 */
Alpine.store('confirmModal', {
    form: null,
    title: '确定继续吗？',
    message: '',
    confirmText: '确认',
    cancelText: '取消',
    variant: 'danger',

    open(form) {
        this.form = form;
        this.title = form?.dataset.confirmTitle || '确定继续吗？';
        this.message = form?.dataset.confirmMessage || '';
        this.confirmText = form?.dataset.confirmText || '确认';
        this.cancelText = form?.dataset.cancelText || '取消';
        this.variant = form?.dataset.confirmVariant || 'danger';

        window.dispatchEvent(new CustomEvent('open-confirm-modal', { detail: { name: 'confirm-action' } }));
    },

    submit() {
        const form = this.form;
        this.close();

        if (form) {
            form.submit();
        }
    },

    close() {
        this.form = null;
        window.dispatchEvent(new CustomEvent('close-confirm-modal', { detail: { name: 'confirm-action' } }));
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
