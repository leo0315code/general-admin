<?php

use App\Support\HealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 健康检查路由
|--------------------------------------------------------------------------
|
| 独立于 routes/web.php：这是运维/监控探针通道，不属于后台页面逻辑。
|
| - GET /health            免登录，供负载均衡/监控探活
| - GET /health/detailed   受保护，逐项自检（DB/迁移/缓存/备份/存储/磁盘/队列）
|
| 深度端点为何不全公开：里面含驱动名、目录、阈值等运维信息，
| 对外暴露等于把部署细节送给攻击者。
|
| 放行规则（任一满足即可）：
|  1. 请求来自内网（CIDR 白名单，见 HealthCheck::isInternalIp）
|  2. 携带 ?token=<HEALTH_TOKEN>（与 .env 配置匹配）
|
| 注意：不能用 FILTER_FLAG_NO_PRIV_RANGE|NO_RES_RANGE 判定内网——
| 两个标志是排除式的，叠加后把所有内网段都排除了（见 isInternalIp 注释）。
|
*/

Route::get('/health', function (HealthCheck $health) {
    return response()->json($health->basic());
})->name('health.basic');

Route::get('/health/detailed', function (Request $request, HealthCheck $health) {
    $ip = $request->ip();
    $expected = (string) config('app.health_token');
    $given = (string) $request->query('token');

    $fromInternal = HealthCheck::isInternalIp($ip);
    $tokenMatch = $expected !== '' && hash_equals($expected, $given);

    if (! $fromInternal && ! $tokenMatch) {
        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized：深度健康检查需要内网访问或有效 token',
        ], 403);
    }

    return response()->json($health->detailed());
})
    // 限制频率：深度端点有真实 DB/磁盘探测，不能被刷
    ->middleware('throttle:30,1')
    ->name('health.detailed');
