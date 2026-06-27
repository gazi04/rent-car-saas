<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
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
            // Lock the vehicle row — concurrent transactions queue behind this.
            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();

            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);

            // Re-check under the lock — the answer can't change until we commit.
            if (! $this->availability->isAvailable($vehicle, $start, $end)) {
                throw new VehicleNotAvailableException('Sorry, this vehicle was just booked by someone else.');
            }

            $price = $this->pricing->calculate($vehicle, $start, $end);

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

    public function confirm(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Pending, BookingStatus::Confirmed);
    }

    public function reject(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Pending, BookingStatus::Cancelled);
    }

    public function markActive(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Confirmed, BookingStatus::Active);
        $booking->update(['started_at' => now()]);
    }

    public function complete(Booking $booking): void
    {
        $this->transition($booking, BookingStatus::Active, BookingStatus::Completed);
        $booking->update(['completed_at' => now()]);
    }

    public function cancel(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Completed) {
            throw new \InvalidArgumentException('Completed bookings cannot be cancelled.');
        }
        $booking->update(['status' => BookingStatus::Cancelled]);
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
