@props([
    'name' => '',
    'label' => null,
    'required' => false,
    'hint' => null,
])

@php
    $hasError = $name !== '' && $errors->has($name);
@endphp

<div {{ $attributes->merge(['class' => '']) }}>
    @if ($label)
        <label for="{{ $name }}" class="label">
            {{ $label }}
            @if ($required)
                <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="field-error-text" role="alert">
            {{ $errors->first($name) }}
        </p>
    @endif
</div>
