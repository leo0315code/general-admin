<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建文章" description="发布新的内容" />
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
        ],
        'errors' => $errors->toArray(),
        'indexUrl' => route('posts.index'),
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
