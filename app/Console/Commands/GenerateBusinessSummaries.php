<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Enums\TenantStatus;
use App\Jobs\GenerateBusinessSummaryJob;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Weekly fan-out for the AI business summary: queues
 * one GenerateBusinessSummaryJob per active tenant whose plan enables the
 * feature. Runs on the central connection — each job initializes its own
 * tenancy context. Tenants without the feature (or suspended) are skipped so
 * no plan silently burns API credit.
 */
#[Signature('ai:generate-business-summaries')]
#[Description('Queue the weekly AI business summary for every eligible tenant')]
class GenerateBusinessSummaries extends Command
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
            if (! $tenant->allowsFeature(PlanFeature::AiBusinessSummary)) {
                continue;
            }

            dispatch(new GenerateBusinessSummaryJob($tenant));
            $dispatched++;
        }

        $this->info(sprintf('Queued %d business summaries.', $dispatched));

        return self::SUCCESS;
    }
}
