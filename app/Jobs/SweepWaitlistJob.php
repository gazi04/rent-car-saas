<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\WaitlistService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-offers freed slots to the waitlist (backlog #2) and catches vehicles that
 * came back without the event firing (#3), for one tenant.
 *
 * Dispatched from the central waitlist:sweep command, so the
 * QueueTenancyBootstrapper does NOT re-initialize tenancy for us — the job
 * initializes (and always ends) tenancy itself.
 *
 * The two features are gated independently, so each pass checks its own toggle:
 * the command only guarantees at least one of them is on.
 */
class SweepWaitlistJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(WaitlistService $waitlist): void
    {
        tenancy()->initialize($this->tenant);

        try {
            $waitlist->purgeExpired();

            if ($this->tenant->allowsFeature(PlanFeature::Waitlist)) {
                $this->sweepWaitlist($waitlist);
            }

            if ($this->tenant->allowsFeature(PlanFeature::StockAlert)) {
                $this->sweepStockAlerts($waitlist);
            }
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Notifies with no freed range, i.e. re-examines every pending entry against
     * real availability. That is what moves the offer down the line when the person
     * told first never booked.
     */
    private function sweepWaitlist(WaitlistService $waitlist): void
    {
        // Only vehicles someone is actually waiting on.
        Vehicle::query()
            ->whereHas('waitlistEntries', fn (Builder $query) => $query->whereNull('notified_at')->whereNotNull('start_date'))
            ->each(function (Vehicle $vehicle) use ($waitlist): void {
                $waitlist->notifyMatching($vehicle);
            });
    }

    /**
     * The republish event covers this in the normal case; this pass is the safety
     * net for one that never reached the queue. notifyStockAlerts() re-checks that
     * the vehicle really is bookable, so no filter on that is needed here.
     */
    private function sweepStockAlerts(WaitlistService $waitlist): void
    {
        Vehicle::query()
            ->whereHas('waitlistEntries', fn (Builder $query) => $query->whereNull('notified_at')->whereNull('start_date'))
            ->each(function (Vehicle $vehicle) use ($waitlist): void {
                $waitlist->notifyStockAlerts($vehicle);
            });
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
        Log::error('Waitlist sweep failed', [
            'tenant_id' => $this->tenant->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
