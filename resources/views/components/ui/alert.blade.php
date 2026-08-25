@props([
    'tone' => 'notice',
    'title' => null,
])

@php
    $tones = [
        'positive' => 'border-positive/20 bg-positive-surface text-positive',
        'critical' => 'border-critical/20 bg-critical-surface text-critical',
        'notice' => 'border-notice/20 bg-notice-surface text-notice',
        'info' => 'border-primary/20 bg-primary/10 text-secondary',
    ];

    // Errors need to reach a screen reader the moment Livewire swaps them in.
    $role = in_array($tone, ['critical'], true) ? 'alert' : 'status';
@endphp

<div {{ $attributes->class(['rounded-panel border p-4 text-sm', $tones[$tone] ?? $tones['notice']]) }} role="{{ $role }}">
    @if ($title)
        <p class="mb-1 font-semibold">{{ $title }}</p>
    @endif
    {{ $slot }}
</div>
