<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Events\BookingRejected;
use App\Exceptions\VehicleNotAvailableException;
use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            [$vehicle, $start, $end, $price] = $this->lockAndValidate($data);

            $booking = Booking::create([
                'reference' => $this->generateReference(),
                'vehicle_id' => $vehicle->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'pickup_location' => $data['pickup_location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'start_date' => $start,
                'end_date' => $end,
                'rate_type' => $price['rate_type'],
                'subtotal' => $price['subtotal'],
                'discount_amount' => $price['discount'],
                'total' => $price['total'],
                'deposit' => $price['deposit'],
                'status' => BookingStatus::Pending,
                'locale' => app()->getLocale(),
            ]);

            BookingCreated::dispatch($booking);

            return $booking;
        });
    }

    /**
     * Walk-in / phone booking: race-safe create that lands Confirmed.
     * Does not fire BookingCreated — no customer "received" email.
     *
     * @param  array<string, mixed>  $data
     */
    public function createManual(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            [$vehicle, $start, $end, $price] = $this->lockAndValidate($data);

            return Booking::create([
                'reference' => $this->generateReference(),
                'vehicle_id' => $vehicle->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'pickup_location' => $data['pickup_location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'start_date' => $start,
                'end_date' => $end,
                'rate_type' => $price['rate_type'],
                'subtotal' => $price['subtotal'],
                'discount_amount' => $price['discount'],
                'total' => $price['total'],
                'deposit' => $price['deposit'],
                'status' => BookingStatus::Confirmed,
                'locale' => app()->getLocale(),
            ]);
        });
    }

    public function confirm(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Pending, BookingStatus::Confirmed);
        BookingConfirmed::dispatch($booking);
    }

    public function reject(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Pending, BookingStatus::Cancelled);
        BookingRejected::dispatch($booking);
    }

    public function markActive(Booking $booking, ?Carbon $startedAt = null, ?int $startOdometer = null): void
    {
        $this->transition($booking, BookingStatus::Confirmed, BookingStatus::Active);
        $booking->update([
            'started_at' => $startedAt ?? now(),
            ...($startOdometer !== null ? ['start_odometer' => $startOdometer] : []),
        ]);
    }

    public function complete(Booking $booking, ?Carbon $completedAt = null, ?int $endOdometer = null): void
    {
        $this->transition($booking, BookingStatus::Active, BookingStatus::Completed);
        $booking->update([
            'completed_at' => $completedAt ?? now(),
            ...($endOdometer !== null ? ['end_odometer' => $endOdometer] : []),
        ]);
    }

    public function cancel(Booking $booking, string $cancelledBy = 'operator'): void
    {
        if ($booking->status === BookingStatus::Completed) {
            throw new \InvalidArgumentException('Completed bookings cannot be cancelled.');
        }
        $booking->update(['status' => BookingStatus::Cancelled]);
        BookingCancelled::dispatch($booking, $cancelledBy);
    }

    /**
     * Lock the vehicle row, parse dates, re-check availability, and calculate price.
     * Must be called inside a DB::transaction.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Vehicle, 1: Carbon, 2: Carbon, 3: array<string, mixed>}
     */
    private function lockAndValidate(array $data): array
    {
        // Lock the vehicle row — concurrent transactions queue behind this.
        $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        // Re-check under the lock — the answer can't change until we commit.
        if (! $this->availability->isAvailable($vehicle, $start, $end)) {
            throw new VehicleNotAvailableException('Sorry, this vehicle was just booked by someone else.');
        }

        $price = $this->pricing->calculate($vehicle, $start, $end);

        return [$vehicle, $start, $end, $price];
    }

    private function transition(Booking $booking, BookingStatus $from, BookingStatus $to): void
    {
        if ($booking->status !== $from) {
            throw new \InvalidArgumentException(
                "Booking must be {$from->value} to transition to {$to->value}, got {$booking->status->value}."
            );
        }
        $booking->update(['status' => $to]);
    }

    private function generateReference(): string
    {
        return 'BK-'.now()->year.'-'.Str::upper(Str::random(6));
    }
}
