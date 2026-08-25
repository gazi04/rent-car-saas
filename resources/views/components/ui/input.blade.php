{{--
    Pure attribute passthrough — no @props, nothing filtered. Several browser
    tests and three flatpickr entrypoints bind to attributes set at the call
    site (#date-range-picker, #listing-start-picker, #listing-end-picker,
    #waitlist-start-picker, #waitlist-end-picker, data-test="customer-*"), so
    anything this component swallows is a silent test failure.
--}}
<input {{ $attributes->class([
    'w-full rounded-control border border-line-strong bg-surface-raised px-3.5 py-2.5 text-sm text-ink',
    'placeholder:text-ink-faint',
    'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30',
    'disabled:cursor-not-allowed disabled:bg-surface-sunken disabled:text-ink-faint',
]) }}>
