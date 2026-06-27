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

        $overlaps = fn ($q) => $q->where('start_date', '<', $end)->where('end_date', '>', $start);

        $bookingConflict = $vehicle->bookings()
            ->whereIn('status', BookingStatus::blocking())
            ->where($overlaps)
            ->exists();

        if ($bookingConflict) {
            return false;
        }

        return ! $vehicle->blockedDates()->where($overlaps)->exists();
    }
}
