@props([
    'pad' => 'md',
    'as' => 'div',
])

@php
    $padding = [
        'none' => '',
        'sm' => 'p-4',
        'md' => 'p-5 sm:p-6',
        'lg' => 'p-6 sm:p-8',
    ][$pad] ?? 'p-5 sm:p-6';
@endphp

<{{ $as }} {{ $attributes->class(['rounded-panel border border-line bg-surface-raised', $padding]) }}>
    {{ $slot }}
</{{ $as }}>
