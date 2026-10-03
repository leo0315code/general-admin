<x-app-layout>
    <x-slot name="header">
        <x-page-header title="发送消息" description="按指定用户 / 角色 / 全员发送站内消息，带实时推送" :breadcrumbs="[['label' => '消息发布', 'url' => route('messages.index')], ['label' => '发送消息']]">
            <x-slot name="actions">
                <a href="{{ route('messages.index') }}" class="btn-secondary">返回历史</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        $composerProps = [
            'action' => route('messages.store'),
            'indexUrl' => route('messages.index'),
            'searchUrl' => route('users.search'),
            'csrf' => csrf_token(),
            'roles' => $roles,
        ];
    @endphp

    <div class="card">
        <div class="card-header">
            <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">填写消息内容</h3>
        </div>

        <x-vue-mount component="message-composer" :props="$composerProps" />
    </div>
</x-app-layout>
