<?php

namespace App\Filament\Operator\Resources\Vehicles\Pages;

use App\Filament\Operator\Resources\Vehicles\VehicleResource;
use App\Filament\Support\PlanLimit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    /**
     * Hide "Create & create another" once the next save would fill the plan's
     * last vehicle slot, so the operator can't chain past the cap onto a blank
     * form with no feedback — plain "Create" then redirects to the (bannered)
     * list.
     */
    public function canCreateAnother(): bool
    {
        return PlanLimit::vehicleCreateAnotherAllowed();
    }

    /**
     * Plan vehicle cap: block only NEW creates once the tenant's fleet is at
     * its plan's limit — existing vehicles are never touched on a downgrade.
     * The always-visible banner (OperatorPanelProvider) is the primary signal;
     * this is the write-time backstop.
     */
    protected function beforeCreate(): void
    {
        if (PlanLimit::vehiclesReached()) {
            Notification::make()
                ->title(__('panel.vehicle_limit_reached_title'))
                ->body(__('panel.vehicle_limit_reached_body', ['limit' => PlanLimit::vehicleLimit()]))
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
