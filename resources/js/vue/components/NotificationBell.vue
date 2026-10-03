<script setup>
// 通知铃铛（顶栏未读徽章）
//
// 通道优先级：WebSocket（GatewayWorker 推送）→ 不可用时退化为 60 秒轮询。
// 推送只作为「有新消息」的信号，真实未读数仍以接口返回为准（避免推送丢包导致计数失真）。
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { resolveWsUrl } from '../composables/useWebSocketEndpoint.js';
import Icon from './Icon.vue';

const props = defineProps({
    count: { type: Number, default: 0 },
    listUrl: { type: String, default: '' },
    unreadUrl: { type: String, default: '' }, // 轮询兜底 + 推送后校正
    ticketUrl: { type: String, default: '' }, // 换 WS 票据
});

const unread = ref(props.count);
const connected = ref(false);

let socket = null;
let timer = null;
let reconnectTimer = null;
let disposed = false;

const badgeText = () => (unread.value > 99 ? '99+' : String(unread.value));

async function refresh() {
    if (document.hidden || !props.unreadUrl) return;

    try {
        const res = await fetch(props.unreadUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        });
        if (!res.ok) return;

        const data = await res.json();
        const before = unread.value;
        unread.value = Number(data.count) || 0;

        if (unread.value > before) {
            window.__ui?.toast?.show('success', `有 ${unread.value - before} 条新通知`, 3000);
        }
    } catch {
        /* 静默：徽章刷新失败不影响页面 */
    }
}

/** 推送到达：先更新计数，再拉一次接口校正 */
function onPushed(payload) {
    if (typeof payload?.unread === 'number') {
        const before = unread.value;
        unread.value = payload.unread;

        if (payload.unread > before && payload.title) {
            window.__ui?.toast?.show('success', payload.title, 4000);
        }
    }

    refresh();
}

function startPolling() {
    if (timer || !props.unreadUrl) return;
    timer = setInterval(refresh, 60000);
}

async function connect() {
    if (!props.ticketUrl || disposed) return;

    try {
        const res = await fetch(props.ticketUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
        });

        if (!res.ok) {
            startPolling();

            return;
        }

        const data = await res.json();

        // 地址按页面协议归一化：有 SSL 走 wss、没有就走 ws，解析不出来则退回轮询
        const endpoint = resolveWsUrl(data.url);

        // 服务端未启用 WS 或地址不可用：直接退化为轮询
        if (!data.enabled || !data.ticket || !endpoint) {
            startPolling();

            return;
        }

        socket = new WebSocket(endpoint);

        socket.onopen = () => {
            socket.send(JSON.stringify({ type: 'auth', ticket: data.ticket }));
        };

        socket.onmessage = (e) => {
            const msg = JSON.parse(e.data || '{}');

            if (msg.type === 'auth') {
                connected.value = !!msg.ok;
                if (!msg.ok) startPolling();

                return;
            }

            if (msg.type === 'notification') {
                onPushed(msg);
            }
        };

        socket.onclose = () => {
            connected.value = false;
            startPolling(); // 断线兜底

            if (disposed) return;
            reconnectTimer = setTimeout(connect, 15000);
        };

        socket.onerror = () => {
            connected.value = false;
            startPolling();
        };
    } catch {
        startPolling();
    }
}

onMounted(() => {
    unread.value = props.count;
    document.addEventListener('visibilitychange', refresh);
    connect();
});

onBeforeUnmount(() => {
    disposed = true;
    document.removeEventListener('visibilitychange', refresh);

    if (timer) clearInterval(timer);
    if (reconnectTimer) clearTimeout(reconnectTimer);
    if (socket) socket.close();
});
</script>

<template>
    <a
        :href="listUrl"
        class="relative inline-flex items-center justify-center p-2 rounded-lg text-gray-500 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
        :title="unread > 0 ? `有 ${unread} 条未读通知` : '通知中心'"
        :aria-label="unread > 0 ? `通知中心，有 ${unread} 条未读通知` : '通知中心'"
    >
        <Icon name="heroicon-o-bell" class="h-5 w-5" />
        <span
            v-show="unread > 0"
            class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-danger-500 text-white text-[10px] font-semibold leading-none"
        >{{ badgeText() }}</span>
        <!-- 连接状态指示：仅在线时显示一个绿点，故障时不打扰用户 -->
        <span
            v-show="connected"
            class="absolute bottom-1 right-1 h-1.5 w-1.5 rounded-full bg-success-500"
            title="实时推送已连接"
        />
    </a>
</template>
