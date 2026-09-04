<?php

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] #[Title('Booking Received')] class extends Component {
    /**
     * Locked: the route's {booking:reference} is the only thing standing between a
     * visitor and someone else's booking details, and it guards the GET alone.
     * Full rationale on vehicle-show.blade.php's $vehicle.
     */
    #[Locked]
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking;
    }
};

?>

<div class="mx-auto w-full max-w-lg px-4 py-8 sm:px-6 sm:py-12">
    <x-ui.card pad="lg" class="text-center">
        <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-positive-surface text-positive">
            <flux:icon.check class="size-7" />
        </div>

        <h1 class="mb-2 text-2xl font-bold text-ink">{{ __('booking.booking_received') }}</h1>

        <div class="mb-4 inline-block rounded-panel bg-surface-sunken px-4 py-2">
            <p class="mb-0.5 text-xs text-ink-muted">{{ __('booking.booking_reference') }}</p>
            <p class="font-mono text-xl font-bold text-ink">{{ $booking->reference }}</p>
        </div>

        <p class="mb-6 text-sm text-ink-muted">{{ __('booking.booking_pending_notice') }}</p>

        <dl class="mb-6 divide-y divide-line rounded-panel bg-surface p-4 text-left text-sm">
            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2">
                <dt class="text-ink-muted">{{ __('booking.vehicle') }}</dt>
                <dd class="font-medium text-ink">{{ $booking->vehicle->name }}</dd>
            </div>
            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2">
                <dt class="text-ink-muted">{{ __('booking.dates') }}</dt>
                <dd class="font-medium text-ink">{{ $booking->start_date->format('d M Y') }} → {{ $booking->end_date->format('d M Y') }}</dd>
            </div>
            <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 py-2">
                <dt class="text-ink-muted">{{ __('booking.total') }}</dt>
                <dd><x-ui.price :amount="$booking->total" size="sm" class="font-bold" /></dd>
            </div>
        </dl>

        <div class="flex flex-col gap-3">
            <x-ui.button :href="route('public.home')" class="w-full">
                {{ __('booking.back_to_fleet') }}
            </x-ui.button>

            <p class="text-center text-sm text-ink-muted">
                @if ($booking->customer_email)
                    {{ __('booking.check_email_to_cancel') }}
                @else
                    {{ __('booking.contact_to_cancel') }}
                @endif
            </p>
        </div>
    </x-ui.card>
</div>
