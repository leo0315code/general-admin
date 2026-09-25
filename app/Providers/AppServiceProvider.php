<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 全局密码强度策略（所有 Password::defaults() 生效处统一约束）：
        // 至少 10 位，同时包含字母与数字；生产环境追加泄露密码库校验。
        // 测试/本地环境不校验 uncompromised（避免依赖 HIBP 网络请求）。
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            if ($this->app->environment('production')) {
                $rule->uncompromised();
            }

            return $rule;
        });

        // 为 Spatie Role 模型补充 users 关联（model_has_roles 多态），
        // 以便 withCount('users') 与 destroy 时判断"已分配用户的角色不可删除"。
        Role::resolveRelationUsing('users', function (Role $role) {
            return $role->belongsToMany(User::class, 'model_has_roles', 'role_id', 'model_id')
                ->wherePivot('model_type', User::class);
        });

        // 加载系统设置进运行时 config（migrate 等无表场景自动跳过）
        $this->loadSettings();
    }

    /**
     * 从 settings 表加载配置到运行时 config：
     * - app.name        站点名称
     * - app.pagination  列表每页条数
     * - app.copyright   页脚版权信息
     *
     * 设置值缓存到「app.settings」键（永久缓存），保存设置时由 SettingController 失效，
     * 避免每个请求都查 settings 表。
     */
    protected function loadSettings(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        try {
            // 缓存只存标量数组：serializable_classes=false 禁止对象反序列化，
            // 存 Collection 对象会在读取时变成 __PHP_Incomplete_Class
            $settings = collect(Cache::rememberForever('app.settings', function () {
                return Setting::query()->pluck('value', 'key')->all();
            }));

            if ($settings->has('site_name') && $settings['site_name'] !== '') {
                config(['app.name' => $settings['site_name']]);
            }

            $pagination = (int) ($settings['pagination'] ?? 0);
            if ($pagination > 0) {
                config(['app.pagination' => $pagination]);
            } else {
                config(['app.pagination' => config('app.pagination', 15)]);
            }

            if ($settings->has('copyright')) {
                config(['app.copyright' => $settings['copyright']]);
            }
        } catch (\Throwable) {
            // 忽略数据库不可用场景（如配置缓存期）
        }
    }
}
