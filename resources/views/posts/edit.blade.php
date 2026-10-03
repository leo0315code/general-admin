<x-app-layout>
    <x-slot name="header">
        <x-page-header title="编辑文章" description="{{ $post->title }}" :back-url="route('posts.index')" :breadcrumbs="[['label' => '文章管理', 'url' => route('posts.index')], ['label' => '编辑文章']]">
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
            'cover_attachment_id' => old('cover_attachment_id', $post->cover_attachment_id),
            'cover_preview_url' => $post->cover_attachment_id
                ? route('posts.cover-preview', $post->cover_attachment_id)
                : '',
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('posts.index'),
        // 无删除按钮权限时不传删除地址，Vue 侧自动隐藏删除按钮
        'destroyUrl' => Gate::check('posts.destroy') ? route('posts.destroy', $post) : '',
        'coverUploadUrl' => route('posts.cover-upload'),
        'maxSizeMb' => (int) config('uploads.max_size') / 1024,
    ];
@endphp

    <div class="card max-w-3xl">
        <x-vue-mount component="post-form" :props="$postFormProps" />
    </div>
</x-app-layout>
