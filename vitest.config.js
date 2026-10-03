import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    test: {
        // 默认 node 环境：现有用例都是纯逻辑（composable / 工具函数），
        // happy-dom 启动一次要几十秒且吃满一个核。真要测 DOM 行为的用例，
        // 在文件第一行写 // @vitest-environment happy-dom 单独开启。
        environment: 'node',
        // 单文件串行：forks 并行池在部分机器上会卡在等待 worker 响应
        fileParallelism: false,
        // 复用 worker 而不是每文件起一个（纯函数用例无状态泄漏风险，省一半启动时间）
        isolate: false,
        include: ['resources/js/vue/__tests__/**/*.spec.js'],
    },
});
