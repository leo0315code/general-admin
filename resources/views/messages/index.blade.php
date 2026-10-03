<x-app-layout>
    <x-slot name="header">
        <x-page-header title="消息发布" description="主动向用户发送站内消息；撤回后将从所有人收件箱中移除">
            <x-slot name="actions">
                @can('messages.create')
                    <a href="{{ route('messages.create') }}" class="btn-primary">
                        <x-icon name="heroicon-o-paper-airplane" class="h-4 w-4" />
                        <span>发送消息</span>
                    </a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        $scopeLabels = [
            'users' => '指定用户',
            'role' => '按角色',
            'all' => '全员',
        ];

        $messagesIndexProps = [
            'broadcasts' => $broadcasts->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'content' => $item->content,
                'scope_label' => $item->scope === 'role'
                    ? '角色：'.($item->role ?? '—')
                    : ($scopeLabels[$item->scope] ?? $item->scope),
                'recipients_count' => $item->recipients_count,
                'sender' => $item->sender->name ?? '系统',
                'created_at' => $item->created_at?->format('Y-m-d H:i'),
                'revoked_at' => $item->revoked_at?->format('Y-m-d H:i'),
                'revoke_url' => route('messages.revoke', $item),
            ])->values(),
            'can' => $can,
        ];
    @endphp

    <div class="card">
        <x-vue-mount component="messages-index" :props="$messagesIndexProps" />

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$broadcasts" />
                <x-pagination :paginator="$broadcasts" />
            </div>
        </div>
    </div>
</x-app-layout>
