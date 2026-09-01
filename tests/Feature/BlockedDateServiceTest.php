<?php

use App\Exceptions\VehicleNotAvailableException;
use App\Models\BlockedDate;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\BlockedDateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

covers(BlockedDateService::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    tenancy()->initialize($this->tenant);
    $this->vehicle = Vehicle::factory()->create();
    $this->service = app(BlockedDateService::class);
});

afterEach(fn () => tenancy()->end());

/** @return array<string, mixed> */
function blockedDateData(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id,
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
        'reason' => 'maintenance',
    ], $overrides);
}

it('creates a blocked date with tenant_id auto-filled', function () {
    $block = $this->service->create(blockedDateData($this->vehicle));

    expect($block)->toBeInstanceOf(BlockedDate::class)
        ->and($block->tenant_id)->toBe($this->tenant->id)
        ->and($block->vehicle_id)->toBe($this->vehicle->id)
        ->and($block->reason)->toBe('maintenance');
});

it('throws VehicleNotAvailableException when an occupying booking overlaps the range', function () {
    // Simulates the race the fix closes: an occupying booking already exists by
    // the time this write runs (SQLite can't hold a true concurrent lock — the
    // Postgres-level guarantee is proven separately in tests/Postgres).
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->create([
        'start_date' => '2030-06-02',
        'end_date' => '2030-06-03',
    ]);

    expect(fn () => $this->service->create(blockedDateData($this->vehicle)))
        ->toThrow(VehicleNotAvailableException::class);

    expect(BlockedDate::query()->count())->toBe(0);
});

it('allows a block that overlaps an existing blocked date', function () {
    // Extending an overrunning maintenance block with a second, overlapping
    // block is a legitimate operator action — hasBookingConflict() only checks
    // occupying bookings, never other blocked dates.
    BlockedDate::factory()->create([
        'vehicle_id' => $this->vehicle->id,
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ]);

    $block = $this->service->create(blockedDateData($this->vehicle, [
        'start_date' => '2030-06-03',
        'end_date' => '2030-06-06',
    ]));

    expect(BlockedDate::query()->count())->toBe(2)
        ->and($block->exists)->toBeTrue();
});

it('rejects an inverted date range', function () {
    expect(fn () => $this->service->create(blockedDateData($this->vehicle, [
        'start_date' => '2030-06-04',
        'end_date' => '2030-06-01',
    ])))->toThrow(InvalidArgumentException::class);

    expect(BlockedDate::query()->count())->toBe(0);
});

it('throws when the vehicle does not exist', function () {
    expect(fn () => $this->service->create(blockedDateData($this->vehicle, [
        'vehicle_id' => 999999,
    ])))->toThrow(ModelNotFoundException::class);
});

it('stores the reason as null when none is given', function () {
    $block = $this->service->create(blockedDateData($this->vehicle, ['reason' => null]));

    expect($block->reason)->toBeNull();
});
