<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Mail\BookingReviewRequestMail;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the next-day review invitation for one tenant:
 * every booking completed yesterday that still has no review and has not already
 * been asked gets a single BookingReviewRequestMail, then is marked so a rerun
 * never emails the customer twice.
 *
 * Dispatched from the central reviews:request-pending command, so the
 * QueueTenancyBootstrapper does NOT re-initialize tenancy for us — the job
 * initializes (and always ends) tenancy itself.
 */
class RequestReviewsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenant);

        try {
            Booking::query()
                ->where('status', BookingStatus::Completed)
                ->whereDate('completed_at', today()->subDay())
                ->whereNull('review_requested_at')
                ->whereNotNull('customer_email')
                ->whereDoesntHave('review')
                ->each(function (Booking $booking): void {
                    Mail::to($booking->customer_email)
                        ->locale($booking->locale ?? $this->tenant->operatorLocale())
                        ->queue(BookingReviewRequestMail::forTenantDomain($booking));

                    $booking->update(['review_requested_at' => now()]);
                });
        } finally {
            tenancy()->end();
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Review request sweep failed', [
            'tenant_id' => $this->tenant->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
