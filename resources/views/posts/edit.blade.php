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

    <div class="card max-w-3xl">
        <form method="POST" action="{{ route('posts.update', $post) }}" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            <x-form-field name="title" label="标题" :required="true">
                <input id="title" name="title" type="text" class="input @error('title') input-error @enderror" value="{{ old('title', $post->title) }}" required autofocus>
            </x-form-field>

            <x-form-field name="content" label="内容" :required="true">
                <textarea id="content" name="content" rows="10" class="input @error('content') input-error @enderror" required>{{ old('content', $post->content) }}</textarea>
            </x-form-field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-form-field name="status" label="状态" :required="true">
                    <select id="status" name="status" class="input @error('status') input-error @enderror">
                        <option value="draft" @selected(old('status', $post->status) === 'draft')>草稿</option>
                        <option value="published" @selected(old('status', $post->status) === 'published')>已发布</option>
                    </select>
                </x-form-field>

                <x-form-field name="published_at" label="发布时间" hint="选填；发布时留空将自动使用当前时间。">
                    <input
                        id="published_at"
                        name="published_at"
                        type="datetime-local"
                        value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"
                        class="input @error('published_at') input-error @enderror"
                    >
                </x-form-field>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="保存修改" icon="heroicon-o-check" />
                <form method="POST" action="{{ route('posts.destroy', $post) }}"
                      data-confirm-title="确定要删除文章「{{ $post->title }}」吗？"
                      data-confirm-message="删除后将进入回收站（软删除），可在回收站中还原。">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2"
                            @click.prevent="window.__ui.confirmModal.open($el.closest('form'))">
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        删除文章
                    </button>
                </form>
            </div>
        </form>
    </div>
</x-app-layout>
