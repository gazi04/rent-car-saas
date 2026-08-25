@props([
    /** 'default' matches the storefront/marketing width; 'narrow' is for prose and single-column forms. */
    'size' => 'default',
])

@php
    $width = match ($size) {
        'narrow' => 'max-w-3xl',
        default => 'max-w-6xl',
    };
@endphp

<div {{ $attributes->class([$width, 'mx-auto w-full px-4 sm:px-6 lg:px-8']) }}>
    {{ $slot }}
</div>
