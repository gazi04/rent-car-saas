<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Vehicle;
use Carbon\Carbon;

class AvailabilityService
{
    public function isAvailable(Vehicle $vehicle, Carbon $start, Carbon $end): bool
    {
        if (! $start->lt($end)) {
            throw new \InvalidArgumentException('start_date must be before end_date.');
        }

        if ($this->hasBookingConflict($vehicle, $start, $end)) {
            return false;
        }

        $overlaps = fn ($q) => $q->where('start_date', '<', $end)->where('end_date', '>', $start);

        return ! $vehicle->blockedDates()->where($overlaps)->exists();
    }

    /**
     * Whether an occupying booking (Pending/Confirmed/Active) overlaps the given
     * half-open range on this vehicle. Shared by isAvailable() and the operator
     * block-dates guard so the interval predicate lives in exactly one place.
     */
    public function hasBookingConflict(Vehicle $vehicle, Carbon $start, Carbon $end): bool
    {
        return $vehicle->bookings()
            ->whereIn('status', BookingStatus::blocking())
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start)
            ->exists();
    }
}
