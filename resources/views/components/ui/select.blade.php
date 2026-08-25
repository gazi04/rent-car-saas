{{-- Pure attribute passthrough — see components/ui/input.blade.php. --}}
<select {{ $attributes->class([
    'w-full rounded-control border border-line-strong bg-surface-raised px-3.5 py-2.5 text-sm text-ink',
    'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-faint',
]) }}>
    {{ $slot }}
</select>
