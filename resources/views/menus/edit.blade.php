<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑节点：{{ $menu->title }}" description="{{ $menu->typeLabel() }} · 权限标识 {{ $menu->permission_name ?? '（未配置）' }}" :back-url="route('menus.index')">
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
        'mode' => 'edit',
        'action' => route('menus.update', $menu),
        'method' => 'PATCH',
        'csrf' => csrf_token(),
        'old' => [
            'pid' => old('pid', $menu->pid),
            'type' => old('type', $menu->type),
            'title' => old('title', $menu->title),
            'permission_name' => old('permission_name', $menu->permission_name),
            'route' => old('route', $menu->route),
            'icon' => old('icon', $menu->icon),
            'sort' => old('sort', $menu->sort),
            'status' => old('status', (bool) $menu->status),
            'remark' => old('remark', $menu->remark),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('menus.index'),
        'destroyUrl' => route('menus.destroy', $menu),
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
        <x-vue-mount component="menu-form" :props="$menuFormProps" />
    </div>
</x-app-layout>
