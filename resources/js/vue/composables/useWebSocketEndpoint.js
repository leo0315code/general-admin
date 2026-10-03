/**
 * WebSocket 地址解析（纯函数，可单测）
 *
 * 同一套代码要同时跑在有 SSL 和没有 SSL 的环境，所以协议不能写死：
 * 能推导就按浏览器实际环境推导，后端显式给了就尊重配置。
 *
 * 规则（从上到下匹配）：
 * 1. 后端没给地址 → 按页面协议推导：https 页面用 wss://，http 页面用 ws://，路径 /ws；
 * 2. 给了相对路径（/ws）或协议相对（//host/ws）→ 协议同样跟随页面；
 * 3. 给了绝对地址（ws:// 或 wss://）：
 *    - 同域名：协议跟随页面（APP_URL 配的协议与实际访问不一致时，以浏览器为准）；
 *    - 跨域名：尊重配置，仅当「https 页面 + ws:// 明文」时升级为 wss ——
 *      否则浏览器会按混合内容直接拦掉；回环地址（localhost/127.0.0.1）是浏览器
 *      白名单，允许 https 页面连明文，保持原样不升级。
 */

/** 浏览器视为可信、允许 https 页面连明文 ws 的回环地址 */
export const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]', '::1'];

/** 允许出现在配置里的协议（http/https 会自动转成 ws/wss） */
export const ALLOWED_SCHEMES = ['ws', 'wss', 'http', 'https'];

/**
 * 解析出可直接 new WebSocket() 的地址；无法解析时返回空串（调用方应降级轮询）
 *
 * @param {string} raw 后端下发的地址（可为空、相对路径或绝对地址）
 * @param {{protocol: string, host: string, hostname: string}} [loc] 页面 location
 * @returns {string}
 */
export function resolveWsUrl(raw = '', loc = window.location) {
    if (!loc) return '';

    const pageProto = loc.protocol === 'https:' ? 'wss:' : 'ws:';
    const value = String(raw ?? '').trim();

    // 1. 没给地址：完全按页面环境推导
    if (!value) {
        return `${pageProto}//${loc.host}/ws`;
    }

    // 0. 带协议前缀时只认 ws/wss/http/https：挡掉 javascript:、ftp: 之类
    const scheme = value.match(/^([a-z][a-z0-9+.-]*):/i);

    if (scheme && !ALLOWED_SCHEMES.includes(scheme[1].toLowerCase())) {
        return '';
    }

    let url;

    try {
        url = new URL(value, `${loc.protocol}//${loc.host}`);
    } catch {
        return '';
    }

    // 2. 相对路径（/ws）或协议相对（//host/ws）：host 已由 base 补齐，协议跟随页面
    if (!scheme) {
        url.protocol = pageProto;

        return url.toString();
    }

    // 3-1. 同域名以浏览器实际协议为准
    if (url.hostname === loc.hostname) {
        url.protocol = pageProto;

        return url.toString();
    }

    // 3-2. 跨域名且是「https 页面 + 明文 ws」：升级为 wss，回环地址除外
    if (url.protocol === 'ws:' && pageProto === 'wss:' && !LOOPBACK_HOSTS.includes(url.hostname)) {
        url.protocol = 'wss:';
    }

    return url.toString();
}
