<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Jobs\ExpireStalePendingBookingsJob;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Hourly fan-out for the pending-booking expiry sweep (deep-audit finding 01):
 * queues one ExpireStalePendingBookingsJob per active tenant.
 *
 * Unlike the other sweeps this one is not plan-gated. A Pending booking blocks
 * its vehicle, so leaving stale ones in place is an inventory bug rather than a
 * missing feature — every tenant gets it, on every plan.
 *
 * Hourly rather than daily because the window is expressed in hours: a daily
 * run would silently add up to 24 hours to whatever operators configured.
 */
#[Signature('bookings:expire-pending')]
#[Description('Cancel bookings left unconfirmed past the expiry window, freeing their vehicles, for every active tenant')]
class ExpireStalePendingBookings extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        // cursor() keeps the Tenant generic; ->get() returns stancl's
        // non-generic TenantCollection and drops the model type.
        $tenants = Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->cursor();

        foreach ($tenants as $tenant) {
            dispatch(new ExpireStalePendingBookingsJob($tenant));
            $dispatched++;
        }

        $this->info(sprintf('Queued pending-booking expiry sweeps for %d tenants.', $dispatched));

        return self::SUCCESS;
    }
}
