<x-app-layout>
    <x-slot name="header">
        <x-page-header title="新建文章" description="发布新的内容" />
    </x-slot>

    <div class="card max-w-3xl">
        <form method="POST" action="{{ route('posts.store') }}" class="p-6 space-y-6">
            @csrf

            <x-form-field name="title" label="标题" :required="true">
                <input id="title" name="title" type="text" class="input @error('title') input-error @enderror" value="{{ old('title') }}" placeholder="请输入文章标题" required autofocus>
            </x-form-field>

            <x-form-field name="content" label="内容" :required="true">
                <textarea id="content" name="content" rows="10" class="input @error('content') input-error @enderror" placeholder="请输入文章内容…" required>{{ old('content') }}</textarea>
            </x-form-field>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-form-field name="status" label="状态" :required="true">
                    <select id="status" name="status" class="input @error('status') input-error @enderror">
                        <option value="draft" @selected(old('status', 'draft') === 'draft')>草稿</option>
                        <option value="published" @selected(old('status') === 'published')>已发布</option>
                    </select>
                </x-form-field>

                <x-form-field name="published_at" label="发布时间" hint="选填；发布时留空将自动使用当前时间。">
                    <input id="published_at" name="published_at" type="datetime-local" value="{{ old('published_at') }}" class="input @error('published_at') input-error @enderror">
                </x-form-field>
            </div>

            <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                <x-submit-button label="创建文章" icon="heroicon-o-plus" />
                <a href="{{ route('posts.index') }}" class="btn-secondary">取消</a>
            </div>
        </form>
    </div>
</x-app-layout>
