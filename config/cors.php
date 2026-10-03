<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 跨域（CORS）配置
    |--------------------------------------------------------------------------
    |
    | 本项目当前是纯服务端渲染的 Web 后台，没有对外 JSON 接口，
    | 因此默认策略是「收紧」：只有显式列进白名单的站点才允许跨域。
    |
    | 白名单用环境变量配置（逗号分隔），未配置时为空数组＝不放行任何跨域请求。
    | 例：CORS_ALLOWED_ORIGINS=https://admin.example.com,https://www.example.com
    |
    */

    /*
     * 只有这些路径会走 CORS 响应头。
     * 后台页面本身（/users 等）不需要跨域，刻意不列入，
     * 避免给整站都加上 Access-Control-* 头、凭空扩大攻击面。
     */
    'paths' => ['api/*', 'health', 'health/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'HEAD', 'OPTIONS'],

    /*
     * 默认空＝拒绝一切跨域来源。需要对接第三方站点时才逐条加进白名单，
     * 不要图省事写 '*'（等于把接口开放给任意网页）。
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    /*
     * 只放行常用的标准请求头，不放 '*'。
     * X-Requested-With 是本前端（Vue + fetch）会带的自定义头，必须单列。
     */
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    /*
     * 预检结果缓存 1 小时，减少浏览器重复的 OPTIONS 请求。
     */
    'max_age' => 3600,

    /*
     * 是否允许携带 Cookie。跨域带 Cookie 风险高（CSRF 面变大），
     * 保持 false；确需跨站登录态时再改为 true，并同步收紧 allowed_origins。
     */
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
