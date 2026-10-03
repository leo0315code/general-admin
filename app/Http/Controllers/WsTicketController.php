<?php

namespace App\Http\Controllers;

use App\Support\Broadcaster;
use App\Support\WsTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebSocket 连接凭据
 *
 * 前端（通知铃铛组件）建连前先来这里换一张一次性票据。
 * 未启用 WS 时返回 enabled=false，前端直接走轮询即可。
 */
class WsTicketController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! Broadcaster::enabled()) {
            return response()->json(['enabled' => false]);
        }

        return response()->json([
            'enabled' => true,
            'url' => (string) config('websocket.public_url'),
            'ticket' => WsTicket::issue($user->getKey()),
            'ttl' => (int) config('websocket.ticket_ttl'),
        ]);
    }
}
