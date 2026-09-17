<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">系统设置</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">配置站点名称、分页大小与版权信息</p>
            </div>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('settings.update') }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            @foreach ($fields as $field)
                <div>
                    <label class="label" for="{{ $field['key'] }}">{{ $field['label'] }}</label>
                    @if ($field['key'] === 'copyright')
                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="text" class="input" value="{{ old($field['key'], $field['value']) }}" placeholder="如：© 2026 xxx 公司">
                    @elseif ($field['key'] === 'pagination')
                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="number" class="input" value="{{ old($field['key'], $field['value']) }}" min="5" max="100">
                    @else
                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="text" class="input" value="{{ old($field['key'], $field['value']) }}">
                    @endif
                    <x-input-error :messages="$errors->get($field['key'])" class="mt-2" />
                </div>
            @endforeach

            <p class="text-xs text-gray-500 dark:text-gray-400">保存后立即生效（站点名称用于后台品牌展示与页面标题）。</p>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    保存设置
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
