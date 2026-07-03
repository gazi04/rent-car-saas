<?php

namespace App\Filament\Operator\Resources\Vehicles\Pages;

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    /**
     * Plan vehicle cap: block only NEW creates once the tenant's fleet is at
     * its plan's limit — existing vehicles are never touched on a downgrade.
     */
    protected function beforeCreate(): void
    {
        $limit = tenant()?->featureLimit(PlanFeature::VehicleLimit);

        if ($limit !== null && Vehicle::query()->count() >= $limit) {
            Notification::make()
                ->title(__('panel.vehicle_limit_reached_title'))
                ->body(__('panel.vehicle_limit_reached_body', ['limit' => $limit]))
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
