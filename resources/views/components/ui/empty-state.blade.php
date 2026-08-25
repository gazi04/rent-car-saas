@props([
    'icon' => 'truck',
    'title' => null,
    /** Optional trailing slot for a CTA. */
    'action' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center rounded-panel border border-dashed border-line px-6 py-16 text-center') }}>
    <flux:icon :icon="$icon" class="mb-4 size-12 text-ink-faint" />

    @if ($title)
        <p class="text-base font-medium text-ink">{{ $title }}</p>
    @endif

    @if (trim($slot) !== '')
        <p class="mt-1 max-w-sm text-sm text-ink-muted">{{ $slot }}</p>
    @endif

    @if ($action)
        <div class="mt-6">{{ $action }}</div>
    @endif
</div>
