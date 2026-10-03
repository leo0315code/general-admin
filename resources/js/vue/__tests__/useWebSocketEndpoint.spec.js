// @vitest-environment node
// 地址解析是纯函数，不需要 DOM：用 node 环境比 happy-dom 快一个量级（省 CPU）
import { describe, it, expect } from 'vitest';
import { resolveWsUrl } from '../composables/useWebSocketEndpoint.js';

/** 构造一个最小的 location 替身（纯函数不依赖真实 window） */
const page = (protocol, host) => ({
    protocol,
    host,
    hostname: host.split(':')[0],
});

const https = page('https:', 'admin.test');
const http = page('http:', 'admin.test');
const local = page('https:', 'admin.test');

describe('resolveWsUrl 协议兼容（有/无 SSL 都要能跑）', () => {
    it('后端没给地址：按页面协议推导 /ws', () => {
        expect(resolveWsUrl('', https)).toBe('wss://admin.test/ws');
        expect(resolveWsUrl(null, http)).toBe('ws://admin.test/ws');
    });

    it('相对路径 /ws：协议跟随页面', () => {
        expect(resolveWsUrl('/ws', https)).toBe('wss://admin.test/ws');
        expect(resolveWsUrl('/ws', http)).toBe('ws://admin.test/ws');
    });

    it('协议相对 //host/ws：协议跟随页面', () => {
        expect(resolveWsUrl('//cdn.test/ws', http)).toBe('ws://cdn.test/ws');
    });

    it('同域名的 wss 在 http 页面下降级为 ws（以浏览器实际协议为准）', () => {
        expect(resolveWsUrl('wss://admin.test/ws', http)).toBe('ws://admin.test/ws');
        expect(resolveWsUrl('ws://admin.test/ws', https)).toBe('wss://admin.test/ws');
    });

    it('跨域名明文：https 页面升级为 wss，否则会被混合内容拦截', () => {
        expect(resolveWsUrl('ws://gw.internal:2346/ws', https)).toBe('wss://gw.internal:2346/ws');
    });

    it('无 SSL 环境：http 页面保持明文 ws 不升级', () => {
        expect(resolveWsUrl('ws://gw.internal:2346/ws', http)).toBe('ws://gw.internal:2346/ws');
    });

    it('回环地址是浏览器白名单：https 页面也保留明文', () => {
        expect(resolveWsUrl('ws://127.0.0.1:2346', local)).toBe('ws://127.0.0.1:2346/');
        expect(resolveWsUrl('ws://localhost:2346', local)).toBe('ws://localhost:2346/');
    });

    it('显式 wss 跨域名：尊重配置不降级', () => {
        expect(resolveWsUrl('wss://gw.test/ws', http)).toBe('wss://gw.test/ws');
    });

    it('带端口的页面：推导时沿用端口', () => {
        expect(resolveWsUrl('/ws', page('http:', 'localhost:8000'))).toBe('ws://localhost:8000/ws');
    });

    it('非法协议返回空串（挡掉 javascript: / ftp: 等注入面）', () => {
        expect(resolveWsUrl('javascript:alert(1)', https)).toBe('');
        expect(resolveWsUrl('ftp://gw.test/ws', https)).toBe('');
    });

    it('不传 location 时安全返回空串（SSR/测试环境）', () => {
        expect(resolveWsUrl('/ws', null)).toBe('');
    });
});
