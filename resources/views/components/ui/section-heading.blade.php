@props([
    'level' => 'h2',
    /** 'section' is the page-section scale; 'panel' is the in-card scale. */
    'scale' => 'section',
    'subheading' => null,
    /** Optional trailing slot, e.g. a "see all →" link. */
    'action' => null,
])

@php
    $size = $scale === 'panel'
        ? 'text-lg font-semibold'
        : 'text-2xl font-bold sm:text-3xl';
@endphp

<div {{ $attributes->class('flex flex-wrap items-end justify-between gap-x-4 gap-y-2') }}>
    <div class="min-w-0">
        <{{ $level }} class="{{ $size }} text-ink">{{ $slot }}</{{ $level }}>
        @if ($subheading)
            <p class="mt-2 text-ink-muted">{{ $subheading }}</p>
        @endif
    </div>

    @if ($action)
        <div class="shrink-0">{{ $action }}</div>
    @endif
</div>
