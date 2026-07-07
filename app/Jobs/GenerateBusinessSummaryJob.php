<?php

namespace App\Jobs;

use App\Models\AiBusinessSummary;
use App\Models\Tenant;
use App\Services\Ai\BusinessSummaryGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates and stores one tenant's weekly AI business summary
 * .
 *
 * Dispatched from the central ai:generate-business-summaries command, so the
 * QueueTenancyBootstrapper does NOT re-initialize tenancy for us — the job
 * initializes (and always ends) tenancy itself so the generator's queries and
 * the stored row are correctly tenant-scoped even if generation throws.
 *
 * updateOrCreate() keyed on the summarized period makes a retry (or an
 * overlapping manual "Generate now" click) update the existing row instead of
 * creating a duplicate — backed by a unique index on
 * (tenant_id, period_start, period_end).
 */
class GenerateBusinessSummaryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(private readonly Tenant $tenant) {}

    public function handle(BusinessSummaryGenerator $generator): void
    {
        tenancy()->initialize($this->tenant);

        try {
            $summary = $generator->generate($this->tenant->operatorLocale());

            AiBusinessSummary::query()->updateOrCreate(
                [
                    'period_start' => $summary['period_start'],
                    'period_end' => $summary['period_end'],
                ],
                ['content' => $summary['content']],
            );
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
        Log::error('Business summary generation failed', [
            'tenant_id' => $this->tenant->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
