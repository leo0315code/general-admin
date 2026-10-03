<x-app-layout>
    <x-slot name="header">
        <x-page-header title="通知中心" description="账号变更与系统事件的站内消息">
            <x-slot name="actions">
                <a
                    href="{{ route('notifications.index', ['filter' => 'all']) }}"
                    class="{{ $filter === 'unread' ? 'btn-secondary' : 'btn-primary' }}"
                >全部</a>
                <a
                    href="{{ route('notifications.index', ['filter' => 'unread']) }}"
                    class="{{ $filter === 'unread' ? 'btn-primary' : 'btn-secondary' }}"
                >仅未读（{{ $unreadCount }}）</a>

                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-secondary">
                            <x-icon name="heroicon-o-check" class="h-4 w-4" />
                            全部已读
                        </button>
                    </form>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
        $notificationsIndexProps = [
            'notifications' => $notifications->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'content' => $item->content,
                'link' => $item->link,
                'is_read' => $item->isRead(),
                'created_at' => $item->created_at?->format('Y-m-d H:i'),
            ])->values(),
            'filter' => $filter,
            'unreadCount' => $unreadCount,
            'base' => rtrim(route('notifications.index'), '/'),
        ];
    @endphp

    <div class="card">
        <x-vue-mount component="notifications-index" :props="$notificationsIndexProps" />

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$notifications" />
                <x-pagination :paginator="$notifications" />
            </div>
        </div>
    </div>
</x-app-layout>
