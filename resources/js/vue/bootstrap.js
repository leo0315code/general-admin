import { createApp } from 'vue';

// 页面组件注册表：Blade 端通过
//   <div data-vue-app data-component="login" data-props='@json($props)'></div>
// 声明挂载点，此处按 data-component 找到对应组件并挂载。
import AppShell from './components/AppShell.vue';
import DictItemsIndex from './components/DictItemsIndex.vue';
import DictTypesIndex from './components/DictTypesIndex.vue';
import LogsIndex from './components/LogsIndex.vue';
import LoginPage from './components/LoginPage.vue';
import MenusIndex from './components/MenusIndex.vue';
import PostsIndex from './components/PostsIndex.vue';
import PostsTrash from './components/PostsTrash.vue';
import RolesIndex from './components/RolesIndex.vue';
import UsersIndex from './components/UsersIndex.vue';
import UsersTrash from './components/UsersTrash.vue';
import UserForm from './components/UserForm.vue';

const registry = {
    'app-shell': AppShell,
    'dict-items-index': DictItemsIndex,
    'dict-types-index': DictTypesIndex,
    'logs-index': LogsIndex,
    login: LoginPage,
    'menus-index': MenusIndex,
    'posts-index': PostsIndex,
    'posts-trash': PostsTrash,
    'roles-index': RolesIndex,
    'users-form': UserForm,
    'users-index': UsersIndex,
    'users-trash': UsersTrash,
};

export function mountVueApps() {
    document.querySelectorAll('[data-vue-app]').forEach((el) => {
        const name = el.dataset.component;
        const Component = registry[name];

        if (!Component) {
            console.warn(`[vue] 未注册组件: ${name}`);
            return;
        }

        let props = {};
        try {
            props = JSON.parse(el.dataset.props || '{}');
        } catch (e) {
            console.warn(`[vue] data-props 解析失败 (${name})`, e);
        }

        createApp(Component, props).mount(el);
    });
}
