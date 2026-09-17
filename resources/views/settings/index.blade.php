<x-app-layout>
    <x-slot name="header">
        <x-page-header title="系统设置" description="配置站点名称、分页大小与版权信息" />
    </x-slot>

    <x-flash-messages />

    <div class="card max-w-2xl">
        <form method="POST" action="{{ route('settings.update') }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            @foreach ($fields as $field)
                <x-form-field :name="$field['key']" :label="$field['label']">
                    @if ($field['key'] === 'copyright')
                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="text" class="input @error($field['key']) input-error @enderror" value="{{ old($field['key'], $field['value']) }}" placeholder="如：© 2026 xxx 公司">
                    @elseif ($field['key'] === 'pagination')
                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="number" class="input @error($field['key']) input-error @enderror" value="{{ old($field['key'], $field['value']) }}" min="5" max="100">
                    @else
                        <input id="{{ $field['key'] }}" name="{{ $field['key'] }}" type="text" class="input @error($field['key']) input-error @enderror" value="{{ old($field['key'], $field['value']) }}">
                    @endif
                </x-form-field>
            @endforeach

            <p class="text-xs text-gray-500 dark:text-gray-400">保存后立即生效（站点名称用于后台品牌展示与页面标题）。</p>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="保存设置" icon="heroicon-o-check" />
            </div>
        </form>
    </div>
</x-app-layout>
