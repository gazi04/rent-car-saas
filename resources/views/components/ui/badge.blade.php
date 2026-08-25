@props([
    'tone' => 'brand',
])

@php
    $tones = [
        'brand' => 'bg-primary/10 text-secondary',
        'neutral' => 'bg-surface-sunken text-ink-muted',
        'positive' => 'bg-positive-surface text-positive',
        'critical' => 'bg-critical-surface text-critical',
        'notice' => 'bg-notice-surface text-notice',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
    $tones[$tone] ?? $tones['brand'],
]) }}>
    {{ $slot }}
</span>
