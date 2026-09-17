@props([
    'paginator' => null,
    'options' => [10, 20, 50, 100],
    'name' => 'per_page',
])

@php
    $current = (int) request()->query($name, config('app.pagination', 15));
    $list = array_values(array_unique(array_merge($options, in_array($current, $options, true) ? [] : [$current])));
    sort($list);
@endphp

<div
    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
    x-data="{
        name: @js($name),
        change(value) {
            const u = new URL(window.location.href);
            u.searchParams.set(this.name, value);
            u.searchParams.set('page', '1');
            window.location.href = u.toString();
        }
    }"
>
    <span class="select-none">每页</span>
    <select
        class="input !w-auto !px-2.5 !py-1.5 text-sm"
        :value="@js((string) $current)"
        @change="change($event.target.value)"
        aria-label="每页条数"
    >
        @foreach ($list as $opt)
            <option value="{{ $opt }}" @selected($current === $opt)>{{ $opt }}</option>
        @endforeach
    </select>
    <span class="select-none">条</span>
</div>
