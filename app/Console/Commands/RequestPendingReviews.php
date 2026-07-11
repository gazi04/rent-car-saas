<?php

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Enums\TenantStatus;
use App\Jobs\RequestReviewsJob;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily fan-out for the review-request email : queues one
 * RequestReviewsJob per active tenant whose plan enables PlanFeature::Reviews.
 * Tenants without the feature keep collecting and displaying reviews freely —
 * the sweep just skips them, so no automatic invitation is sent.
 */
#[Signature('reviews:request-pending')]
#[Description('Queue next-day review-request emails for every eligible tenant')]
class RequestPendingReviews extends Command
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
            if (! $tenant->allowsFeature(PlanFeature::Reviews)) {
                continue;
            }

            RequestReviewsJob::dispatch($tenant);
            $dispatched++;
        }

        $this->info("Queued review requests for {$dispatched} tenants.");

        return self::SUCCESS;
    }
}
