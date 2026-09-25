<x-app-layout>
    <x-slot name="header">
        <x-page-header title="系统设置" description="配置站点名称、分页大小与版权信息" />
    </x-slot>

    <x-flash-messages />

    @php
        $fieldMeta = [
            'pagination' => ['type' => 'number', 'min' => 5, 'max' => 100, 'hint' => '列表默认每页条数，取值 5-100。'],
            'copyright' => ['type' => 'text', 'placeholder' => '如：© 2026 xxx 公司'],
            'site_name' => ['type' => 'text', 'hint' => '用于后台品牌展示与浏览器标签页标题。'],
        ];

        $settingsProps = [
            'action' => route('settings.update'),
            'method' => 'PUT',
            'csrf' => csrf_token(),
            'fields' => collect($fields)->map(fn ($field) => array_merge([
                'key' => $field['key'],
                'label' => $field['label'],
                'value' => old($field['key'], $field['value']),
                'type' => 'text',
            ], $fieldMeta[$field['key']] ?? []))->values(),
            'errors' => $errors->toArray(),
            'indexUrl' => route('settings.index'),
        ];
    @endphp

    <div class="card max-w-2xl">
        <div
            data-vue-app
            data-component="settings-form"
            data-props='{!! vue_props($settingsProps) !!}'
            x-ignore
        ></div>
    </div>
</x-app-layout>
