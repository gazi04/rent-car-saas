@props([
    'variant' => 'primary',
    'size' => 'md',
    /** When set, renders an <a> instead of a <button>. */
    'href' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-primary text-on-primary hover:bg-secondary',
        'secondary' => 'border border-line-strong bg-surface-raised text-ink hover:bg-surface-sunken',
        'ghost' => 'text-ink-muted hover:bg-surface-sunken hover:text-ink',
        'danger' => 'bg-critical text-on-primary hover:opacity-90',
        'on-dark' => 'bg-surface-raised text-ink hover:bg-surface-sunken',
    ];

    // Every size clears 44px (WCAG 2.5.5) — 'sm' is narrower and lighter, not
    // shorter. A button small enough to look neat is still a button someone has
    // to hit with a thumb.
    $sizes = [
        'sm' => 'min-h-11 px-3.5 py-2 text-sm',
        'md' => 'min-h-11 px-5 py-2.5 text-sm',
        'lg' => 'min-h-12 px-6 py-3 text-base',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-60',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
