<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">编辑文章</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $post->title }}</p>
            </div>
            <a href="{{ route('posts.index') }}" class="btn-secondary">
                <x-icon name="heroicon-o-arrow-left" class="h-4 w-4" />
                返回列表
            </a>
        </div>
    </x-slot>

    <x-flash-messages />

    <div class="card max-w-3xl">
        <form method="POST" action="{{ route('posts.update', $post) }}" class="p-6 space-y-6">
            @csrf
            @method('PATCH')

            <div>
                <label class="label" for="title">标题</label>
                <input id="title" name="title" type="text" class="input" value="{{ old('title', $post->title) }}" required autofocus>
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="content">内容</label>
                <textarea id="content" name="content" rows="10" class="input" required>{{ old('content', $post->content) }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="status">状态</label>
                    <select id="status" name="status" class="input">
                        <option value="draft" @selected(old('status', $post->status) === 'draft')>草稿</option>
                        <option value="published" @selected(old('status', $post->status) === 'published')>已发布</option>
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>

                <div>
                    <label class="label" for="published_at">发布时间</label>
                    <input
                        id="published_at"
                        name="published_at"
                        type="datetime-local"
                        value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"
                        class="input"
                    >
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">选填；发布时留空将自动使用当前时间。</p>
                    <x-input-error :messages="$errors->get('published_at')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-check" class="h-4 w-4" />
                    保存修改
                </button>
                <form method="POST" action="{{ route('posts.destroy', $post) }}" class="inline" onsubmit="return confirm('确定要删除文章「{{ $post->title }}」吗？');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger-ghost border border-red-200 dark:border-red-500/30 rounded-lg px-4 py-2">
                        <x-icon name="heroicon-o-trash" class="h-4 w-4" />
                        删除文章
                    </button>
                </form>
            </div>
        </form>
    </div>
</x-app-layout>
