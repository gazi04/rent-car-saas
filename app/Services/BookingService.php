<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PlanFeature;
use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Events\BookingRejected;
use App\Exceptions\PromoCodeInvalidException;
use App\Exceptions\VehicleNotAvailableException;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PromoCode;
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
            [$vehicle, $start, $end] = $this->lockAndValidate($data);
            $customer = $this->resolveCustomer($data);
            $promo = $this->resolvePromo($data, $customer);
            $price = $this->pricing->calculate($vehicle, $start, $end, $promo);

            $booking = Booking::create([
                'reference' => $this->generateReference(),
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'promo_code_id' => $promo?->id,
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

            $promo?->increment('uses_count');

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
            [$vehicle, $start, $end] = $this->lockAndValidate($data);
            $customer = $this->resolveCustomer($data);
            $promo = $this->resolvePromo($data, $customer);
            $price = $this->pricing->calculate($vehicle, $start, $end, $promo);

            $booking = Booking::create([
                'reference' => $this->generateReference(),
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'promo_code_id' => $promo?->id,
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

            $promo?->increment('uses_count');

            return $booking;
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
        $this->transition($booking, BookingStatus::Confirmed, BookingStatus::Active, [
            'started_at' => $startedAt ?? now(),
            ...($startOdometer !== null ? ['start_odometer' => $startOdometer] : []),
        ]);
    }

    public function complete(Booking $booking, ?Carbon $completedAt = null, ?int $endOdometer = null): void
    {
        $this->transition($booking, BookingStatus::Active, BookingStatus::Completed, [
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
     * Lock the vehicle row, parse dates, and re-check availability. Must be
     * called inside a DB::transaction. Pricing is computed by the caller (after
     * resolving any promo code).
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Vehicle, 1: Carbon, 2: Carbon}
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

        return [$vehicle, $start, $end];
    }

    /**
     * Resolve and validate the promo code on the booking data (if any) for this
     * customer. Returns null when no code is given or the feature is off (the
     * code is silently ignored). Locks the promo row so a global usage cap can't
     * be exceeded by concurrent redemptions. Must run inside the transaction.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PromoCodeInvalidException
     */
    private function resolvePromo(array $data, Customer $customer): ?PromoCode
    {
        $code = strtoupper(trim((string) ($data['promo_code'] ?? '')));

        if ($code === '' || ! (tenant()?->allowsFeature(PlanFeature::PromoCodes) ?? true)) {
            return null;
        }

        $promo = PromoCode::query()->where('code', $code)->lockForUpdate()->first();

        if ($promo === null || ! $promo->isCurrentlyValid()) {
            throw new PromoCodeInvalidException('This promo code is not valid.');
        }

        if ($promo->per_customer_limit !== null
            && $customer->bookings()->where('promo_code_id', $promo->id)->count() >= $promo->per_customer_limit) {
            throw new PromoCodeInvalidException('This promo code has already been used.');
        }

        return $promo;
    }

    /**
     * Race-safe state transition: a single conditional UPDATE guarded by the
     * expected current status, so two concurrent operators acting on the same
     * booking can never both succeed (the loser's affected-row count is 0).
     * Extra attributes ride in the same statement, keeping status + timestamps
     * atomic.
     *
     * @param  array<string, mixed>  $extra
     */
    private function transition(Booking $booking, BookingStatus $from, BookingStatus $to, array $extra = []): void
    {
        $updated = Booking::whereKey($booking->getKey())
            ->where('status', $from->value)
            ->update([
                'status' => $to->value,
                'updated_at' => now(),
                ...$extra,
            ]);

        if ($updated === 0) {
            $booking->refresh();

            throw new \InvalidArgumentException(
                "Booking must be {$from->value} to transition to {$to->value}, got {$booking->status->value}."
            );
        }

        $booking->refresh();
    }

    /**
     * Find-or-create the customer directory record for this booking, matched by
     * phone within the current tenant (the customers.[tenant_id, phone] unique
     * key). Runs inside the booking transaction, under the vehicle row lock, so
     * every create path links a customer exactly once. Keeps the stored name /
     * email in sync with the latest booking.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): Customer
    {
        $customer = Customer::firstOrCreate(
            ['phone' => $data['customer_phone']],
            ['name' => $data['customer_name'], 'email' => $data['customer_email'] ?? null],
        );

        if (! $customer->wasRecentlyCreated) {
            $customer->fill([
                'name' => $data['customer_name'],
                'email' => $data['customer_email'] ?? null,
            ])->save();
        }

        return $customer;
    }

    private function generateReference(): string
    {
        return 'BK-'.now()->year.'-'.Str::upper(Str::random(6));
    }
}
