<?php

namespace App\Filament\Operator\Resources\Staff\Pages;

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Disabled (not hidden) at the plan's staff-seat cap so the owner
            // sees why they can't add more. CreateStaff::beforeCreate() is the
            // enforcing backstop.
            CreateAction::make()
                ->disabled(fn (): bool => self::atStaffSeatLimit())
                ->tooltip(fn (): ?string => self::atStaffSeatLimit()
                    ? (string) __('panel.staff_seat_limit_reached_body', ['limit' => tenant()?->featureLimit(PlanFeature::StaffSeatLimit)])
                    : null),
        ];
    }

    /**
     * Whether this tenant is at its plan's staff-seat cap. Counts staff rows
     * only (the owner is never counted); null limit = unlimited.
     */
    public static function atStaffSeatLimit(): bool
    {
        $limit = tenant()?->featureLimit(PlanFeature::StaffSeatLimit);

        return $limit !== null
            && User::query()->where('tenant_id', tenant('id'))->where('role', 'staff')->count() >= $limit;
    }
}
