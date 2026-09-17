@php
    $messages = [
        'success' => session('success'),
        'error' => session('error'),
    ];
    $styles = [
        'success' => 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300',
        'error' => 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300',
    ];
    $icons = [
        'success' => 'heroicon-o-check-circle',
        'error' => 'heroicon-o-x-circle',
    ];
@endphp

<div class="space-y-3 mb-4">
    @foreach ($messages as $type => $message)
        @if ($message)
            <div x-data="{ show: true }" x-show="show" x-transition.opacity
                 class="flex items-start gap-2.5 px-4 py-3 rounded-lg border {{ $styles[$type] }}">
                <x-icon :name="$icons[$type]" class="h-5 w-5 shrink-0 mt-0.5" />
                <p class="text-sm flex-1">{{ $message }}</p>
                <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100 transition" aria-label="关闭">
                    <x-icon name="heroicon-o-x-mark" class="h-4 w-4" />
                </button>
            </div>
        @endif
    @endforeach
</div>
