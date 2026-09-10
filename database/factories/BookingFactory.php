<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\RateType;
use App\Models\Booking;
use App\Models\Vehicle;
use App\Support\BookingReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * `tenant_id` is intentionally omitted — BelongsToTenant fills it from context.
     * `vehicle_id` defaults to a new Vehicle factory; use `forVehicle()` in tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+7 days');
        $end = fake()->dateTimeBetween($start, '+10 days');
        $subtotal = fake()->randomFloat(2, 40, 500);

        return [
            'vehicle_id' => Vehicle::factory(),
            'reference' => BookingReference::generate(),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_email' => fake()->optional(0.7)->safeEmail(),
            'pickup_location' => fake()->optional(0.5)->streetAddress(),
            'notes' => fake()->optional(0.3)->sentence(),
            'start_date' => $start,
            'end_date' => $end,
            'rate_type' => RateType::Daily,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total' => $subtotal,
            'deposit' => 0,
            'status' => BookingStatus::Pending,
            'locale' => 'sq',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Completed,
            'started_at' => now()->subDays(3),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => BookingStatus::Cancelled,
        ]);
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (array $attributes): array => [
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
