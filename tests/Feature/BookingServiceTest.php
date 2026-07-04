<?php

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Events\BookingRejected;
use App\Exceptions\VehicleNotAvailableException;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\BookingService;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    tenancy()->initialize($this->tenant);
    $this->vehicle = Vehicle::factory()->create(['daily_rate' => 50, 'weekly_rate' => null, 'monthly_rate' => null]);
    $this->service = app(BookingService::class);
});

afterEach(fn () => tenancy()->end());

/** @return array<string, mixed> */
function bookingData(Vehicle $vehicle, array $overrides = []): array
{
    return array_merge([
        'vehicle_id' => $vehicle->id,
        'customer_name' => 'Test Customer',
        'customer_phone' => '+38344000000',
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ], $overrides);
}

it('creates a pending booking with a BK reference and correct totals', function () {
    $booking = $this->service->create(bookingData($this->vehicle));

    expect($booking->status)->toBe(BookingStatus::Pending)
        ->and($booking->reference)->toMatch('/^BK-\d{4}-[A-Z0-9]{6}$/')
        ->and($booking->tenant_id)->toBe($this->tenant->id)
        ->and((float) $booking->total)->toBe(150.0); // 3 days * 50
});

it('throws VehicleNotAvailableException for an already booked slot', function () {
    $this->service->create(bookingData($this->vehicle));

    $this->service->create(bookingData($this->vehicle));
})->throws(VehicleNotAvailableException::class);

it('throws VehicleNotAvailableException on the re-check under lock', function () {
    // Simulate the race condition: first booking fills the slot.
    Booking::factory()->forVehicle($this->vehicle)->confirmed()->create([
        'start_date' => '2030-06-01',
        'end_date' => '2030-06-04',
    ]);

    // Second create must fail even though no lock is truly contested in SQLite.
    // Documents: true parallel FOR UPDATE guarantee is Postgres-level.
    $this->service->create(bookingData($this->vehicle));
})->throws(VehicleNotAvailableException::class);

it('fires BookingCreated event on successful create', function () {
    // Only fake BookingCreated — faking all events blocks Eloquent model events
    // (creating/created) that BelongsToTenant uses to auto-fill tenant_id.
    Event::fake([BookingCreated::class]);

    $this->service->create(bookingData($this->vehicle));

    Event::assertDispatched(BookingCreated::class);
});

it('transitions pending → confirmed', function () {
    $booking = $this->service->create(bookingData($this->vehicle));
    $this->service->confirm($booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

it('transitions pending → cancelled via reject', function () {
    $booking = $this->service->create(bookingData($this->vehicle));
    $this->service->reject($booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled);
});

it('transitions confirmed → active and sets started_at', function () {
    $booking = $this->service->create(bookingData($this->vehicle));
    $this->service->confirm($booking);
    $this->service->markActive($booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Active)
        ->and($booking->fresh()->started_at)->not->toBeNull();
});

it('transitions active → completed and sets completed_at', function () {
    $booking = $this->service->create(bookingData($this->vehicle));
    $this->service->confirm($booking);
    $this->service->markActive($booking);
    $this->service->complete($booking);

    expect($booking->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($booking->fresh()->completed_at)->not->toBeNull();
});

it('throws on illegal transition complete from pending', function () {
    $booking = $this->service->create(bookingData($this->vehicle));

    $this->service->complete($booking);
})->throws(InvalidArgumentException::class);

it('throws when cancelling a completed booking', function () {
    $booking = $this->service->create(bookingData($this->vehicle));
    $this->service->confirm($booking);
    $this->service->markActive($booking);
    $this->service->complete($booking);

    $this->service->cancel($booking->fresh());
})->throws(InvalidArgumentException::class);

it('booking in tenant A is invisible from tenant B context', function () {
    $this->service->create(bookingData($this->vehicle));
    tenancy()->end();

    $tenantB = Tenant::factory()->create();
    tenancy()->initialize($tenantB);

    expect(Booking::count())->toBe(0);
});

it('rejects a concurrent conflicting transition via the status-guarded update', function () {
    $booking = $this->service->create(bookingData($this->vehicle));

    // Second operator holds a stale copy that still reads Pending.
    $stale = Booking::query()->findOrFail($booking->id);

    $this->service->confirm($booking);

    Event::fake();

    expect(fn () => $this->service->reject($stale))
        ->toThrow(InvalidArgumentException::class);

    expect($stale->refresh()->status)->toBe(BookingStatus::Confirmed);
    Event::assertNotDispatched(BookingRejected::class);
});

it('writes status and timestamp in one atomic update on markActive', function () {
    $booking = $this->service->create(bookingData($this->vehicle));
    $this->service->confirm($booking);

    $this->service->markActive($booking, startOdometer: 12345);

    $fresh = Booking::query()->findOrFail($booking->id);

    expect($fresh->status)->toBe(BookingStatus::Active)
        ->and($fresh->started_at)->not->toBeNull()
        ->and($fresh->start_odometer)->toBe(12345)
        ->and($booking->status)->toBe(BookingStatus::Active); // in-memory model synced
});
