<?php

use App\Enums\BookingStatus;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use Carbon\Carbon;

covers(AvailabilityService::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    tenancy()->initialize($this->tenant);
    $this->vehicle = Vehicle::factory()->create();
    $this->service = app(AvailabilityService::class);
});

afterEach(fn () => tenancy()->end());

// Helpers for readable date windows.
function window(string $start, string $end): array
{
    return [Carbon::parse($start), Carbon::parse($end)];
}

it('returns true when no bookings or blocks exist', function () {
    [$start, $end] = window('2030-01-10 10:00', '2030-01-12 10:00');

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeTrue();
});

it('returns false when a pending booking overlaps', function () {
    [$start, $end] = window('2030-01-10', '2030-01-15');

    Booking::factory()->forVehicle($this->vehicle)->create([
        'status' => BookingStatus::Pending,
        'start_date' => '2030-01-11',
        'end_date' => '2030-01-13',
    ]);

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeFalse();
});

it('returns false when a confirmed booking overlaps', function () {
    [$start, $end] = window('2030-01-10', '2030-01-15');

    Booking::factory()->forVehicle($this->vehicle)->confirmed()->create([
        'start_date' => '2030-01-11',
        'end_date' => '2030-01-13',
    ]);

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeFalse();
});

it('returns false when an active booking overlaps', function () {
    [$start, $end] = window('2030-01-10', '2030-01-15');

    Booking::factory()->forVehicle($this->vehicle)->active()->create([
        'start_date' => '2030-01-11',
        'end_date' => '2030-01-13',
    ]);

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeFalse();
});

it('returns true when only completed or cancelled bookings overlap', function () {
    [$start, $end] = window('2030-01-10', '2030-01-15');

    Booking::factory()->forVehicle($this->vehicle)->completed()->create([
        'start_date' => '2030-01-11',
        'end_date' => '2030-01-13',
    ]);
    Booking::factory()->forVehicle($this->vehicle)->cancelled()->create([
        'start_date' => '2030-01-12',
        'end_date' => '2030-01-14',
    ]);

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeTrue();
});

it('allows back-to-back bookings when new start equals existing end', function () {
    // Existing booking ends on Jan 13. New booking starts Jan 13 — half-open boundary.
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->create([
        'start_date' => '2030-01-10',
        'end_date' => '2030-01-13',
    ]);

    [$start, $end] = window('2030-01-13', '2030-01-15');

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeTrue();
});

it('returns false when a manual blocked date overlaps', function () {
    [$start, $end] = window('2030-01-10', '2030-01-15');

    BlockedDate::factory()->forVehicle($this->vehicle)->create([
        'start_date' => '2030-01-12',
        'end_date' => '2030-01-14',
    ]);

    expect($this->service->isAvailable($this->vehicle, $start, $end))->toBeFalse();
});

it('rejects a range that ends before it starts', function () {
    [$start, $end] = window('2030-01-15', '2030-01-10');

    expect(fn () => $this->service->isAvailable($this->vehicle, $start, $end))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a zero-length range', function () {
    // start must be strictly before end — an instant is not a rental.
    [$start, $end] = window('2030-01-10 09:00', '2030-01-10 09:00');

    expect(fn () => $this->service->isAvailable($this->vehicle, $start, $end))
        ->toThrow(InvalidArgumentException::class);
});
