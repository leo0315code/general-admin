<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-2xl text-gray-900 dark:text-gray-100 leading-tight">新建文章</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">发布新的内容</p>
            </div>
        </div>
    </x-slot>

    <div class="card max-w-3xl">
        <form method="POST" action="{{ route('posts.store') }}" class="p-6 space-y-6">
            @csrf

            <div>
                <label class="label" for="title">标题</label>
                <input id="title" name="title" type="text" class="input" value="{{ old('title') }}" placeholder="请输入文章标题" required autofocus>
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <label class="label" for="content">内容</label>
                <textarea id="content" name="content" rows="10" class="input" placeholder="请输入文章内容…" required>{{ old('content') }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-2" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="label" for="status">状态</label>
                    <select id="status" name="status" class="input">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>草稿</option>
                        <option value="published" @selected(old('status') === 'published')>已发布</option>
                    </select>
                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                </div>

                <div>
                    <label class="label" for="published_at">发布时间</label>
                    <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at') }}" class="input">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">选填；发布时留空将自动使用当前时间。</p>
                    <x-input-error :messages="$errors->get('published_at')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <button type="submit" class="btn-primary">
                    <x-icon name="heroicon-o-plus" class="h-4 w-4" />
                    创建文章
                </button>
                <a href="{{ route('posts.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
