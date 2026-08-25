@php
    /**
     * Shared vehicle card. The $horizontal variant went with the two-column
     * listing layout; there is one card shape now.
     *
     * Off-the-road vehicles are still listed (backlog #3) — dimmed, and the CTA
     * goes to the detail page to join the stock alert rather than promising a
     * booking it cannot honour.
     */
    $isBookable = $vehicle->status === \App\Enums\VehicleStatus::Available;

    /** Carry the listing's date filter forward so the visitor re-picks nothing. */
    $cardDateParams = ($startDate ?? '') !== '' && ($endDate ?? '') !== ''
        ? ['start_date' => $startDate, 'end_date' => $endDate]
        : [];
@endphp

<div class="flex h-full flex-col overflow-hidden rounded-panel border border-line bg-surface-raised transition-shadow hover:shadow-md {{ $isBookable ? '' : 'opacity-75' }}">
    {{-- Cover photo --}}
    <div class="aspect-video overflow-hidden bg-surface-sunken">
        @if ($vehicle->getFirstMedia('vehicle_photos'))
            <img src="{{ $vehicle->getFirstMediaUrl('vehicle_photos', 'web') }}"
                 alt="{{ $vehicle->name }}"
                 loading="lazy"
                 class="h-full w-full object-cover">
        @else
            <div class="flex h-full w-full items-center justify-center text-ink-faint">
                <flux:icon.truck class="size-12" />
            </div>
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <div class="mb-2 flex items-start justify-between gap-2">
            <h2 class="text-sm font-semibold leading-tight text-ink">{{ $vehicle->name }}</h2>
            <x-ui.badge class="shrink-0">{{ $vehicle->category->getLabel() }}</x-ui.badge>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-muted">
            <span>{{ __('booking.seats', ['count' => $vehicle->seats]) }}</span>
            <span>{{ $vehicle->fuel_type->getLabel() }}</span>
            <span>{{ $vehicle->transmission->getLabel() }}</span>
        </div>

        @unless ($isBookable)
            <x-ui.badge tone="neutral" class="mb-3 self-start">{{ __('booking.vehicle_unavailable_badge') }}</x-ui.badge>
        @endunless

        <div class="mt-auto flex flex-wrap items-center justify-between gap-3">
            <x-ui.price :amount="$vehicle->daily_rate" :per="__('booking.per_day')" />

            <x-ui.button size="sm"
                         :variant="$isBookable ? 'primary' : 'secondary'"
                         :href="route('public.vehicle', $vehicle) . ($cardDateParams ? '?' . http_build_query($cardDateParams) : '')">
                {{ $isBookable ? __('booking.book_now') : __('booking.stock_alert_submit') }}
            </x-ui.button>
        </div>
    </div>
</div>
