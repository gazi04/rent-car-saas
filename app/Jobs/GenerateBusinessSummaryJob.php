<?php

namespace App\Jobs;

use App\Models\AiBusinessSummary;
use App\Models\Tenant;
use App\Services\Ai\BusinessSummaryGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates and stores one tenant's weekly AI business summary
 * .
 *
 * Dispatched from the central ai:generate-business-summaries command, so the
 * QueueTenancyBootstrapper does NOT re-initialize tenancy for us — the job
 * initializes (and always ends) tenancy itself so the generator's queries and
 * the stored row are correctly tenant-scoped even if generation throws.
 */
class GenerateBusinessSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(BusinessSummaryGenerator $generator): void
    {
        $days = (int) config('ai.summary_period_days');

        tenancy()->initialize($this->tenant);

        try {
            $content = $generator->generate($this->tenant->operatorLocale());

            AiBusinessSummary::query()->create([
                'content' => $content,
                'period_start' => now()->subDays($days)->startOfDay(),
                'period_end' => now()->startOfDay(),
            ]);
        } finally {
            tenancy()->end();
        }
    }
}
