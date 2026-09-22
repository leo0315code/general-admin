<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑文章" description="{{ $post->title }}" :back-url="route('posts.index')">
            <x-slot name="actions">
                <a href="{{ route('posts.index') }}" class="btn-secondary">
                    <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                    返回列表
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    @php
    $postFormProps = [
        'mode' => 'edit',
        'action' => route('posts.update', $post),
        'method' => 'PUT',
        'csrf' => csrf_token(),
        'old' => [
            'title' => old('title', $post->title),
            'content' => old('content', $post->content),
            'status' => old('status', $post->status),
            'published_at' => old('published_at', $post->published_at?->format('Y-m-d\TH:i')),
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('posts.index'),
        'destroyUrl' => route('posts.destroy', $post),
    ];
@endphp

    <div class="card max-w-3xl">
        <div
            data-vue-app
            data-component="post-form"
            data-props='{!! vue_props($postFormProps) !!}'
            x-ignore
        ></div>
    </div>
</x-app-layout>
