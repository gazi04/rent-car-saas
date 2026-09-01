<?php

namespace App\Filament\Operator\Resources\Staff\Pages;

use App\Filament\Operator\Resources\Staff\StaffResource;
use App\Filament\Support\HelpAction;
use App\Filament\Support\PlanLimit;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('staff'),
            // Disabled (not hidden) at the plan's staff-seat cap so the owner
            // sees why they can't add more. The always-visible banner
            // (OperatorPanelProvider) is the primary notice; this button state
            // and CreateStaff::beforeCreate() are the backstops.
            CreateAction::make()
                ->disabled(fn (): bool => PlanLimit::staffSeatsReached())
                ->tooltip(fn (): ?string => PlanLimit::staffSeatsReached()
                    ? (string) __('panel.staff_seat_limit_reached_body', ['limit' => PlanLimit::staffSeatLimit()])
                    : null),
        ];
    }
}
