{{-- Rate table + Book Now CTA. The CTA is swapped for an "unavailable" notice when
     the vehicle is off the road (backlog #3): the page still renders so the stock
     alert below it can be joined, but the booking page would 404 anyway. --}}
<x-ui.card>
    <h2 class="mb-4 text-lg font-semibold text-ink">{{ __('booking.rates_heading') }}</h2>

    <dl class="space-y-2 text-sm">
        @if ($vehicle->hourly_rate)
            <div class="flex items-center justify-between gap-4">
                <dt class="text-ink-muted">{{ __('booking.per_hour') }}</dt>
                <dd><x-ui.price :amount="$vehicle->hourly_rate" size="sm" /></dd>
            </div>
        @endif
        <div class="flex items-center justify-between gap-4">
            <dt class="text-ink-muted">{{ __('booking.per_day') }}</dt>
            <dd><x-ui.price :amount="$vehicle->daily_rate" size="sm" /></dd>
        </div>
        @if ($vehicle->weekly_rate)
            <div class="flex items-center justify-between gap-4">
                <dt class="text-ink-muted">{{ __('booking.per_week') }}</dt>
                <dd><x-ui.price :amount="$vehicle->weekly_rate" size="sm" /></dd>
            </div>
        @endif
        @if ($vehicle->monthly_rate)
            <div class="flex items-center justify-between gap-4">
                <dt class="text-ink-muted">{{ __('booking.per_month') }}</dt>
                <dd><x-ui.price :amount="$vehicle->monthly_rate" size="sm" /></dd>
            </div>
        @endif
        @if ($vehicle->deposit)
            <div class="mt-2 flex items-center justify-between gap-4 border-t border-line pt-2">
                <dt class="text-ink-muted">{{ __('booking.deposit') }}</dt>
                <dd><x-ui.price :amount="$vehicle->deposit" size="sm" /></dd>
            </div>
        @endif
    </dl>

    @php
        $rateDateParams = ($startDate ?? '') !== '' && ($endDate ?? '') !== ''
            ? ['start_date' => $startDate, 'end_date' => $endDate]
            : [];
    @endphp

    @if ($isBookable ?? true)
        <x-ui.button size="lg"
                     class="mt-6 w-full"
                     :href="route('public.vehicle.book', $vehicle) . ($rateDateParams ? '?' . http_build_query($rateDateParams) : '')">
            {{ __('booking.book_now') }}
        </x-ui.button>
    @else
        <div class="mt-6 rounded-control bg-surface-sunken px-4 py-3 text-center text-sm font-medium text-ink-muted">
            {{ __('booking.vehicle_unavailable_notice') }}
        </div>
    @endif
</x-ui.card>
