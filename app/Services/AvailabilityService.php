<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class AvailabilityService
{
    public function isAvailable(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end): bool
    {
        throw_unless($start->lt($end), InvalidArgumentException::class, 'start_date must be before end_date.');

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
    public function hasBookingConflict(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $vehicle->bookings()
            ->whereIn('status', BookingStatus::blocking())
            ->where('start_date', '<', $end)
            ->where('end_date', '>', $start)
            ->exists();
    }
}
