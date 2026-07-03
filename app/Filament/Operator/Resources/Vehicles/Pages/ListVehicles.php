<?php

namespace App\Filament\Operator\Resources\Vehicles\Pages;

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Disabled (not hidden) at the plan's vehicle cap so the operator
            // sees why they can't add more. CreateVehicle::beforeCreate() is
            // the enforcing backstop.
            CreateAction::make()
                ->disabled(fn (): bool => self::atVehicleLimit())
                ->tooltip(fn (): ?string => self::atVehicleLimit()
                    ? (string) __('panel.vehicle_limit_reached_body', ['limit' => tenant()?->featureLimit(PlanFeature::VehicleLimit)])
                    : null),
        ];
    }

    protected static function atVehicleLimit(): bool
    {
        $limit = tenant()?->featureLimit(PlanFeature::VehicleLimit);

        return $limit !== null && Vehicle::query()->count() >= $limit;
    }
}
