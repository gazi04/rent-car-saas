<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\VehicleNotAvailableException;
use App\Models\BlockedDate;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Blocked dates take a vehicle off the market the same way a booking does, so
 * creating one needs the same guarantee bookings already have: lock the vehicle
 * row, re-check under the lock, then write. Without this, a customer's booking
 * and an operator's block can each pass their own point-in-time check and both
 * commit, leaving the vehicle booked and out of service for the same dates
 * (deep-audit finding 04).
 */
class BlockedDateService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data): BlockedDate
    {
        return DB::transaction(function () use ($data) {
            // Lock the vehicle row — the same mutex BookingService::lockAndValidate()
            // uses, so a concurrent booking and a concurrent block on this vehicle
            // are always serialized against each other, regardless of which table
            // either one writes to.
            $vehicle = Vehicle::query()->whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();

            $start = Date::parse($data['start_date']);
            $end = Date::parse($data['end_date']);

            throw_unless($start->lt($end), InvalidArgumentException::class, 'start_date must be before end_date.');

            // Re-check under the lock. Only occupying bookings, not other blocked
            // dates — extending an overrunning maintenance block with a second,
            // overlapping block stays a legal operator action.
            throw_if(
                $this->availability->hasBookingConflict($vehicle, $start, $end),
                VehicleNotAvailableException::class,
                'This vehicle has an occupying booking in that window.'
            );

            return BlockedDate::query()->create([
                'vehicle_id' => $vehicle->id,
                'start_date' => $start,
                'end_date' => $end,
                'reason' => $data['reason'] ?? null,
            ]);
        });
    }
}
