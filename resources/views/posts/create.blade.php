<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建文章" description="发布新的内容" :breadcrumbs="[['label' => '文章管理', 'url' => route('posts.index')], ['label' => '新建文章']]" />
    </x-slot>

    @php
    $postFormProps = [
        'mode' => 'create',
        'action' => route('posts.store'),
        'method' => 'POST',
        'csrf' => csrf_token(),
        'old' => [
            'title' => old('title', ''),
            'content' => old('content', ''),
            'status' => old('status', 'draft'),
            'published_at' => old('published_at', ''),
            'cover_attachment_id' => old('cover_attachment_id'),
            'cover_preview_url' => '',
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('posts.index'),
        'coverUploadUrl' => route('posts.cover-upload'),
        'maxSizeMb' => (int) config('uploads.max_size') / 1024,
    ];
@endphp

    <div class="card max-w-3xl">
        <x-vue-mount component="post-form" :props="$postFormProps" />
    </div>
</x-app-layout>
