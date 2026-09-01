<?php

namespace App\Filament\Support;

use App\Enums\PlanFeature;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;

/**
 * Single source of truth for the operator panel's plan-cap checks. Used by the
 * list-page "New …" button state, the create-page backstop hooks, and the
 * always-visible "limit reached" banner registered in OperatorPanelProvider.
 *
 * A null limit means unlimited (see Tenant::featureLimit()).
 */
final class PlanLimit
{
    public static function vehicleLimit(): ?int
    {
        return Tenant::current()?->featureLimit(PlanFeature::VehicleLimit);
    }

    public static function vehiclesReached(): bool
    {
        $limit = self::vehicleLimit();

        return $limit !== null && Vehicle::query()->count() >= $limit;
    }

    /**
     * Whether "Create & create another" should still be offered — false once the
     * next save would fill the last slot, so the operator is funnelled into the
     * normal single-create redirect back to the (bannered) list.
     */
    public static function vehicleCreateAnotherAllowed(): bool
    {
        $limit = self::vehicleLimit();

        return $limit === null || Vehicle::query()->count() + 1 < $limit;
    }

    public static function staffSeatLimit(): ?int
    {
        return Tenant::current()?->featureLimit(PlanFeature::StaffSeatLimit);
    }

    public static function staffSeatsReached(): bool
    {
        $limit = self::staffSeatLimit();

        return $limit !== null && self::staffCount() >= $limit;
    }

    public static function staffCreateAnotherAllowed(): bool
    {
        $limit = self::staffSeatLimit();

        return $limit === null || self::staffCount() + 1 < $limit;
    }

    /**
     * Staff rows only — the owner is never counted against the seat cap. User is
     * central (not tenant-scoped), so the tenant filter is explicit.
     */
    private static function staffCount(): int
    {
        return User::query()
            ->where('tenant_id', tenant('id'))
            ->where('role', 'staff')
            ->count();
    }
}
