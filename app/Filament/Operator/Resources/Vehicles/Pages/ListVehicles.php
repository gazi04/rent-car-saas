<?php

namespace App\Filament\Operator\Resources\Vehicles\Pages;

use App\Filament\Operator\Resources\Vehicles\VehicleResource;
use App\Filament\Support\HelpAction;
use App\Filament\Support\PlanLimit;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('vehicles'),
            // Disabled (not hidden) at the plan's vehicle cap so the operator
            // sees why they can't add more. The always-visible banner
            // (OperatorPanelProvider) is the primary notice; this button state
            // and CreateVehicle::beforeCreate() are the backstops.
            CreateAction::make()
                ->disabled(fn (): bool => PlanLimit::vehiclesReached())
                ->tooltip(fn (): ?string => PlanLimit::vehiclesReached()
                    ? (string) __('panel.vehicle_limit_reached_body', ['limit' => PlanLimit::vehicleLimit()])
                    : null),
        ];
    }
}
