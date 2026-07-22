<?php

namespace App\Listeners;

use App\Enums\PlanFeature;
use App\Events\BookingCancelled;
use App\Events\BookingRejected;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\WaitlistService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the waitlist when a booking releases its dates (backlog #2).
 *
 * Both events matter and are easy to conflate: reject() dispatches
 * BookingRejected and cancel() dispatches BookingCancelled, but both land the
 * booking on Cancelled and both free the dates. Handling only one silently loses
 * half the free-ups. Laravel discovers any public `handle*` method by its
 * first-parameter type (see DiscoverEvents), so the two methods below are both
 * registered — do NOT also Event::listen() this, or every free-up notifies twice.
 *
 * Dispatched from tenant context, so QueueTenancyBootstrapper restores tenancy
 * for us — unlike the central-command jobs, this must not initialize it itself.
 */
class NotifyWaitlistOnBookingFreed implements ShouldQueue
{
    public function __construct(private readonly WaitlistService $waitlist) {}

    public function handleBookingCancelled(BookingCancelled $event): void
    {
        $this->notify($event->booking);
    }

    public function handleBookingRejected(BookingRejected $event): void
    {
        $this->notify($event->booking);
    }

    private function notify(Booking $booking): void
    {
        $tenant = Tenant::query()->find($booking->tenant_id);

        if ($tenant === null || ! $tenant->allowsFeature(PlanFeature::Waitlist)) {
            return;
        }

        $vehicle = $booking->vehicle;

        if ($vehicle === null) {
            return;
        }

        $this->waitlist->notifyMatching($vehicle, $booking->start_date, $booking->end_date);
    }
}
