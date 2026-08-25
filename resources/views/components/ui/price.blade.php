@props([
    'amount',
    /** Optional trailing unit, e.g. "per day". */
    'per' => null,
    'size' => 'md',
])

@php
    $sizes = [
        'sm' => 'text-sm font-semibold',
        'md' => 'text-lg font-bold',
        'lg' => 'text-3xl font-bold',
    ];
@endphp

{{-- The single place the currency symbol lives. Kosovo prices in EUR, but
     hardcoding € in ten templates made that a fact you could not change. --}}
<span {{ $attributes->class('inline-flex items-baseline gap-1') }}>
    <span class="{{ $sizes[$size] ?? $sizes['md'] }} text-ink">{{ __('booking.currency_symbol') }}{{ number_format((float) $amount, 2) }}</span>
    @if ($per)
        <span class="text-xs text-ink-faint">{{ $per }}</span>
    @endif
</span>
