<?php

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Enums\TenantStatus;
use App\Jobs\ProcessVehicleMaintenanceJob;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily fan-out for vehicle maintenance reminders + auto-block (operator
 * feature #10): queues one ProcessVehicleMaintenanceJob per active tenant
 * whose plan enables PlanFeature::MaintenanceReminders. Tenants without the
 * feature keep logging service records freely — the sweep just skips them,
 * so no reminder is sent and no vehicle is auto-blocked.
 */
#[Signature('maintenance:process-due')]
#[Description('Queue vehicle maintenance reminders and auto-block for every eligible tenant')]
class ProcessVehicleMaintenance extends Command
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
            if (! $tenant->allowsFeature(PlanFeature::MaintenanceReminders)) {
                continue;
            }

            ProcessVehicleMaintenanceJob::dispatch($tenant);
            $dispatched++;
        }

        $this->info("Queued maintenance processing for {$dispatched} tenants.");

        return self::SUCCESS;
    }
}
