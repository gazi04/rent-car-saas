<?php

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Booking Received')] class extends Component {
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking;
    }
};

?>

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
        <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('booking.booking_received') }}</h1>

        <div class="inline-block rounded-lg bg-gray-100 px-4 py-2 mb-4">
            <p class="text-xs text-gray-500 mb-0.5">{{ __('booking.booking_reference') }}</p>
            <p class="text-xl font-mono font-bold text-gray-900">{{ $booking->reference }}</p>
        </div>

        <p class="text-sm text-gray-600 mb-6">{{ __('booking.booking_pending_notice') }}</p>

        <dl class="text-left divide-y divide-gray-100 text-sm mb-6 bg-gray-50 rounded-lg p-4">
            <div class="py-2 flex justify-between">
                <dt class="text-gray-500">{{ __('booking.vehicle') }}</dt>
                <dd class="font-medium">{{ $booking->vehicle->name }}</dd>
            </div>
            <div class="py-2 flex justify-between">
                <dt class="text-gray-500">{{ __('booking.dates') }}</dt>
                <dd class="font-medium">{{ $booking->start_date->format('d M Y') }} → {{ $booking->end_date->format('d M Y') }}</dd>
            </div>
            <div class="py-2 flex justify-between">
                <dt class="text-gray-500">{{ __('booking.total') }}</dt>
                <dd class="font-bold">€{{ number_format((float) $booking->total, 2) }}</dd>
            </div>
        </dl>

        <div class="flex flex-col gap-3">
            <a href="{{ route('public.home') }}"
               class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-secondary transition-colors text-center">
                {{ __('booking.back_to_fleet') }}
            </a>

            <p class="text-sm text-gray-500 text-center">
                @if ($booking->customer_email)
                    {{ __('booking.check_email_to_cancel') }}
                @else
                    {{ __('booking.contact_to_cancel') }}
                @endif
            </p>
        </div>
    </div>
</div>
