<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建菜单 / 权限节点" description="保存后会自动创建对应权限记录，可直接在角色管理中勾选" :back-url="route('menus.index')">
            <x-slot name="actions">
                <a href="{{ route('menus.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
    $iconSuggestions = ['heroicon-o-squares-2x2', 'heroicon-o-document-text', 'heroicon-o-users', 'heroicon-o-shield-check', 'heroicon-o-rectangle-stack', 'heroicon-o-bookmark-square', 'heroicon-o-clipboard-document-list', 'heroicon-o-cog-6-tooth', 'heroicon-o-chart-bar', 'heroicon-o-truck', 'heroicon-o-shopping-cart'];
    $typeHints = [
        \App\Models\Menu::TYPE_DIR => '仅作侧边栏分组标题，不参与鉴权',
        \App\Models\Menu::TYPE_MENU => '可导航页面，权限标识决定菜单是否可见',
        \App\Models\Menu::TYPE_BUTTON => '页面内操作点（如「新增用户」），用 @can 控制显隐',
    ];
    $menuFormProps = [
        'mode' => 'create',
        'action' => route('menus.store'),
        'method' => 'POST',
        'csrf' => csrf_token(),
        'old' => [
            'pid' => old('pid', $selectedPid ?? 0),
            'type' => old('type', $selectedType ?? \App\Models\Menu::TYPE_MENU),
            'title' => old('title', ''),
            'permission_name' => old('permission_name', ''),
            'route' => old('route', ''),
            'icon' => old('icon', ''),
            'sort' => old('sort', 0),
            'status' => old('status', true),
            'remark' => old('remark', ''),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('menus.index'),
        'parents' => collect($parents)->map(fn ($row) => [
            'id' => $row['menu']->id,
            'label' => str_repeat('\u3000', $row['depth']) . ($row['depth'] > 0 ? '└ ' : '') . $row['menu']->title . '（' . $row['menu']->typeLabel() . '）',
        ])->values(),
        'typeOptions' => collect(\App\Models\Menu::TYPE_LABELS)->map(fn ($label, $value) => [
            'value' => $value,
            'label' => $label,
            'hint' => $typeHints[$value],
        ])->values(),
        'routeSuggestions' => $routeSuggestions,
        'iconSuggestions' => $iconSuggestions,
    ];
@endphp

    <div class="card">
        <div
            data-vue-app
            data-component="menu-form"
            data-props='{!! vue_props($menuFormProps) !!}'
            x-ignore
        ></div>
    </div>
</x-app-layout>
