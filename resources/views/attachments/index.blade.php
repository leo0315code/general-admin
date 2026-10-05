<x-app-layout>
    <x-slot name="header">
        <x-page-header title="附件管理" description="文件存于私有磁盘，下载需鉴权；类型与体积双重白名单">
            <x-slot name="actions">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    单文件上限 {{ number_format($maxSizeKb / 1024, 1) }} MB
                </span>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-flash-messages />

    {{-- 上传区：Blade 原生表单（multipart 交给浏览器，不走 Vue，最稳） --}}
    @can('attachments.upload')
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100">上传附件</h3>
            </div>
            @php
                $uploaderProps = [
                    'action' => route('attachments.store'),
                    'csrf' => csrf_token(),
                    'maxSizeKb' => (int) config('uploads.max_size'),
                    'hint' => '允许：图片（jpg/png/gif/webp…）、文档（pdf/office/csv/txt）、压缩包（zip/rar/7z）；不含 svg / html / php',
                ];
            @endphp
            <x-vue-mount component="attachment-uploader" :props="$uploaderProps" />

            @error('file')
                <p class="px-5 pb-4 -mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
            @enderror
        </div>
    @endcan

    @php
        $attachmentsIndexProps = [
            'attachments' => $attachments->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'extension' => $item->extension,
                'mime' => $item->mime,
                'size_human' => $item->humanSize(),
                'is_image' => $item->isImage(),
                'uploader' => $item->user->name ?? null,
                'created_at' => $item->created_at?->format('Y-m-d H:i'),
                'download_url' => route('attachments.download', $item),
            ])->values(),
            'can' => $can,
            'base' => rtrim(route('attachments.index'), '/'),
            'keyword' => $keyword,
        ];
    @endphp

    <div class="card">
        <x-vue-mount component="attachments-index" :props="$attachmentsIndexProps" />

        <div class="px-5 py-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-per-page :paginator="$attachments" />
                <x-pagination :paginator="$attachments" />
            </div>
        </div>
    </div>
</x-app-layout>
