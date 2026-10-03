<?php

/*
 * 由 APP_URL 推导浏览器可见的 WS 地址：http(s) 换为 ws(s)，沿用域名与端口，路径 /ws。
 * 这样生产环境只需开 WS_ENABLED=true，不必再单独维护一个 WS 域名。
 */
$derivePublicUrl = static function (): string {
    $parts = parse_url((string) env('APP_URL', 'http://localhost')) ?: [];
    $scheme = ($parts['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';
    $host = $parts['host'] ?? 'localhost';
    $port = isset($parts['port']) ? ':'.$parts['port'] : '';

    return $scheme.'://'.$host.$port.'/ws';
};

return [

    /*
    |--------------------------------------------------------------------------
    | WebSocket（GatewayWorker）
    |--------------------------------------------------------------------------
    |
    | 站内通知的实时推送通道。未启用（WS_ENABLED=false）时全部调用静默降级，
    | 页面退化为轮询未读数接口，功能不受影响。
    |
    | 端口说明：
    | - register：Register 服务地址，Gateway / BusinessWorker 与推送端共用；
    | - listen：Gateway 对外监听地址（浏览器连的就是这个）；
    | - start_port：BusinessWorker 与 Gateway 内部通信起始端口。
    |
    */

    'enabled' => (bool) env('WS_ENABLED', false),

    /*
     * 浏览器可见的 WS 地址（ws:// 或 wss://）。
     *
     * 默认由 APP_URL 推导：http → ws、https → wss，域名与端口沿用，路径统一 /ws
     * （生产由 Nginx 把 /ws 反代到 Gateway 端口）。因此正常情况下只需要开
     * WS_ENABLED=true，不必再单独配一个域名。
     *
     * 仅当 WS 与 HTTP 不同域名/端口时才显式覆盖 WS_PUBLIC_URL —— 典型场景是
     * 本地开发直连 Gateway（APP_URL 是 https://admin.test，但 WS 明文跑在 2346）。
     * 也可以直接写相对路径 "/ws"：此时由前端按页面协议决定 ws/wss，
     * 没有 SSL 的环境不用为协议操心。
     */
    'public_url' => env('WS_PUBLIC_URL') ?: $derivePublicUrl(),

    /** Register 地址：Laravel 推送端（GatewayClient）与 Worker 进程都要连 */
    'register_address' => env('WS_REGISTER_ADDRESS', '127.0.0.1:1236'),

    /** Worker 进程配置（供 gatewayworker/start_*.php 使用） */
    'server' => [
        'listen' => env('WS_LISTEN', '0.0.0.0:2346'),
        'lan_ip' => env('WS_LAN_IP', '127.0.0.1'),
        'start_port' => (int) env('WS_START_PORT', 2300),
        'count' => (int) env('WS_WORKER_COUNT', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | 连接票据
    |--------------------------------------------------------------------------
    |
    | WS 握手无法复用 Cookie 会话（同源策略下浏览器也不会带上 HttpOnly Cookie
    | 给独立端口的 WS），因此改为：登录用户先向 HTTP 接口换一张一次性票据，
    | WS 建连后再用票据换取 uid 绑定。票据 60 秒有效、一次性消费。
    |
    */
    'ticket_ttl' => (int) env('WS_TICKET_TTL', 60),
];
