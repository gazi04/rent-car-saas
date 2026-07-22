<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Enums\TenantStatus;
use App\Jobs\SweepWaitlistJob;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily fan-out for the waitlist (backlog #2) and the stock alert (#3): queues one
 * SweepWaitlistJob per active tenant whose plan enables either feature. The job
 * decides which of its two passes actually run — the features are gated
 * independently, so a tenant can have one without the other.
 *
 * The event listeners notify immediately; this sweep is what makes both features
 * honest over time. It offers a slot to the next person in line when the first
 * never booked, catches dates freed by anything other than a cancellation (a
 * removed maintenance block, say), covers any missed event, and retires entries
 * that can no longer serve anyone.
 */
#[Signature('waitlist:sweep')]
#[Description('Notify waitlisted and stock-alert customers whose vehicle is now bookable, for every eligible tenant')]
class SweepWaitlist extends Command
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
            if (! $tenant->allowsFeature(PlanFeature::Waitlist) && ! $tenant->allowsFeature(PlanFeature::StockAlert)) {
                continue;
            }

            dispatch(new SweepWaitlistJob($tenant));
            $dispatched++;
        }

        $this->info(sprintf('Queued waitlist sweeps for %d tenants.', $dispatched));

        return self::SUCCESS;
    }
}
