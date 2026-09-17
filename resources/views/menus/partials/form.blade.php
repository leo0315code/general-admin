@php
    $currentPid = (int) old('pid', $menu?->pid ?? $selectedPid ?? 0);
    $currentType = old('type', $menu?->type ?? $selectedType ?? \App\Models\Menu::TYPE_MENU);
    $typeHints = [
        \App\Models\Menu::TYPE_DIR => '仅作侧边栏分组标题，不参与鉴权',
        \App\Models\Menu::TYPE_MENU => '可导航页面，权限标识决定菜单是否可见',
        \App\Models\Menu::TYPE_BUTTON => '页面内操作点（如「新增用户」），用 @can 控制显隐',
    ];
@endphp

<x-form-field name="pid" label="上级节点" :required="true">
    <select id="pid" name="pid" class="input @error('pid') input-error @enderror">
        <option value="0" @selected($currentPid === 0)>顶级节点</option>
        @foreach ($parents as $row)
            <option value="{{ $row['menu']->id }}" @selected($currentPid === $row['menu']->id)>
                {{ str_repeat('　', $row['depth']) }}{{ $row['depth'] > 0 ? '└ ' : '' }}{{ $row['menu']->title }}（{{ $row['menu']->typeLabel() }}）
            </option>
        @endforeach
    </select>
</x-form-field>

<x-form-field name="type" label="节点类型" :required="true">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
        @foreach (\App\Models\Menu::TYPE_LABELS as $value => $label)
            <label class="flex items-start gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 px-3.5 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                <input
                    type="radio"
                    name="type"
                    value="{{ $value }}"
                    class="mt-0.5 rounded-full border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                    @checked($currentType === $value)
                >
                <span>
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ $label }}</span>
                    <span class="block mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $typeHints[$value] }}</span>
                </span>
            </label>
        @endforeach
    </div>
</x-form-field>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-form-field name="title" label="显示名称" :required="true">
        <input id="title" name="title" type="text" class="input @error('title') input-error @enderror" value="{{ old('title', $menu?->title) }}" placeholder="如：用户管理" required>
    </x-form-field>

    <x-form-field name="permission_name" label="权限标识" hint="留空则不参与鉴权（纯目录可留空）。保存后自动同步到权限表，角色授权时按此标识勾选。">
        <input id="permission_name" name="permission_name" type="text" class="input font-mono @error('permission_name') input-error @enderror" value="{{ old('permission_name', $menu?->permission_name) }}" placeholder="如：users.create">
    </x-form-field>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-form-field name="route" label="路由名" hint="目录与按钮节点留空；需填写真实存在的路由名，否则菜单不可点击。">
        <input id="route" name="route" type="text" class="input font-mono @error('route') input-error @enderror" value="{{ old('route', $menu?->route) }}" list="route-suggestions" placeholder="如：users.index">
        <datalist id="route-suggestions">
            @foreach ($routeSuggestions as $name)
                <option value="{{ $name }}"></option>
            @endforeach
        </datalist>
    </x-form-field>

    <x-form-field name="icon" label="图标" hint="Heroicons 2.0 名称（heroicon-o-*），留空显示占位方块。">
        <input id="icon" name="icon" type="text" class="input font-mono @error('icon') input-error @enderror" value="{{ old('icon', $menu?->icon) }}" list="icon-suggestions" placeholder="如：heroicon-o-users">
        <datalist id="icon-suggestions">
            @foreach (['heroicon-o-squares-2x2', 'heroicon-o-document-text', 'heroicon-o-users', 'heroicon-o-shield-check', 'heroicon-o-rectangle-stack', 'heroicon-o-bookmark-square', 'heroicon-o-clipboard-document-list', 'heroicon-o-cog-6-tooth', 'heroicon-o-chart-bar', 'heroicon-o-truck', 'heroicon-o-shopping-cart'] as $icon)
                <option value="{{ $icon }}"></option>
            @endforeach
        </datalist>
    </x-form-field>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <x-form-field name="sort" label="排序" hint="数字越小越靠前，同级之间比较。">
        <input id="sort" name="sort" type="number" min="0" max="9999" class="input @error('sort') input-error @enderror" value="{{ old('sort', $menu?->sort ?? 0) }}">
    </x-form-field>

    <x-form-field name="status" label="状态">
        <label class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-700 px-3.5 py-2.5 cursor-pointer">
            <input type="hidden" name="status" value="0">
            <input
                id="status"
                name="status"
                type="checkbox"
                value="1"
                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 shadow-sm focus:ring-primary-500"
                @checked(old('status', $menu?->status ?? true))
            >
            <span class="text-sm text-gray-700 dark:text-gray-200">启用（停用后不出现在侧边栏）</span>
        </label>
    </x-form-field>
</div>

<x-form-field name="remark" label="备注" hint="权限说明（会写入权限表的描述字段，选填）。">
    <input id="remark" name="remark" type="text" class="input @error('remark') input-error @enderror" value="{{ old('remark', $menu?->remark) }}" placeholder="权限说明（会写入权限表的描述字段，选填）">
</x-form-field>
