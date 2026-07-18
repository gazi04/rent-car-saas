{{-- Rate table + Book Now CTA. The CTA is swapped for an "unavailable" notice when
     the vehicle is off the road (backlog #3): the page still renders so the stock
     alert below it can be joined, but the booking page would 404 anyway. --}}
<div class="bg-white rounded-lg border border-gray-200 p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('booking.rates_heading') }}</h2>

    <dl class="space-y-2 text-sm">
        @if ($vehicle->hourly_rate)
            <div class="flex items-center justify-between">
                <dt class="text-gray-500">{{ __('booking.per_hour') }}</dt>
                <dd class="font-semibold text-gray-900">€{{ number_format((float) $vehicle->hourly_rate, 2) }}</dd>
            </div>
        @endif
        <div class="flex items-center justify-between">
            <dt class="text-gray-500">{{ __('booking.per_day') }}</dt>
            <dd class="font-semibold text-gray-900">€{{ number_format((float) $vehicle->daily_rate, 2) }}</dd>
        </div>
        @if ($vehicle->weekly_rate)
            <div class="flex items-center justify-between">
                <dt class="text-gray-500">{{ __('booking.per_week') }}</dt>
                <dd class="font-semibold text-gray-900">€{{ number_format((float) $vehicle->weekly_rate, 2) }}</dd>
            </div>
        @endif
        @if ($vehicle->monthly_rate)
            <div class="flex items-center justify-between">
                <dt class="text-gray-500">{{ __('booking.per_month') }}</dt>
                <dd class="font-semibold text-gray-900">€{{ number_format((float) $vehicle->monthly_rate, 2) }}</dd>
            </div>
        @endif
        @if ($vehicle->deposit)
            <div class="flex items-center justify-between border-t border-gray-100 pt-2 mt-2">
                <dt class="text-gray-500">{{ __('booking.deposit') }}</dt>
                <dd class="font-semibold text-gray-900">€{{ number_format((float) $vehicle->deposit, 2) }}</dd>
            </div>
        @endif
    </dl>

    @if ($isBookable ?? true)
        <a href="{{ route('public.vehicle.book', $vehicle) }}"
           class="mt-6 block text-center rounded-md bg-primary px-4 py-3 text-base font-semibold text-white hover:bg-secondary transition-colors">
            {{ __('booking.book_now') }}
        </a>
    @else
        <div class="mt-6 rounded-md bg-gray-100 px-4 py-3 text-center text-sm font-medium text-gray-500">
            {{ __('booking.vehicle_unavailable_notice') }}
        </div>
    @endif
</div>
