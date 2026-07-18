<?php

namespace App\Listeners;

use App\Enums\PlanFeature;
use App\Events\VehicleBecameAvailable;
use App\Models\Tenant;
use App\Services\WaitlistService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tells the dateless waitlist entries when their vehicle is bookable again (#3).
 *
 * Laravel discovers any public `handle*` method by its first-parameter type (see
 * DiscoverEvents), so the method below is registered by that alone — do NOT also
 * Event::listen() this, or every republish notifies twice.
 *
 * Dispatched from tenant context (the operator editing the vehicle), so
 * QueueTenancyBootstrapper restores tenancy for us — unlike the central-command
 * jobs, this must not initialize it itself.
 */
class NotifyStockAlertOnVehicleAvailable implements ShouldQueue
{
    public function __construct(private readonly WaitlistService $waitlist) {}

    public function handleVehicleBecameAvailable(VehicleBecameAvailable $event): void
    {
        $tenant = Tenant::find($event->vehicle->tenant_id);

        if ($tenant === null || ! $tenant->allowsFeature(PlanFeature::StockAlert)) {
            return;
        }

        $this->waitlist->notifyStockAlerts($event->vehicle);
    }
}
