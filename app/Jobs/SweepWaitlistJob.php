<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\WaitlistService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-offers freed slots to the waitlist (backlog #2), for one tenant.
 *
 * Dispatched from the central waitlist:sweep command, so the
 * QueueTenancyBootstrapper does NOT re-initialize tenancy for us — the job
 * initializes (and always ends) tenancy itself.
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

            // Only vehicles someone is actually waiting on.
            Vehicle::query()
                ->whereHas('waitlistEntries', fn ($query) => $query->whereNull('notified_at')->whereNotNull('start_date'))
                ->each(function (Vehicle $vehicle) use ($waitlist): void {
                    $waitlist->notifyMatching($vehicle);
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
        Log::error('Waitlist sweep failed', [
            'tenant_id' => $this->tenant->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
