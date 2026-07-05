<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // tenant_id omitted — BelongsToTenant fills it from the active tenant.
        return [
            'booking_id' => Booking::factory(),
            'vehicle_id' => Vehicle::factory(),
            'customer_id' => null,
            'reviewer_name' => $this->faker->name(),
            'rating' => $this->faker->numberBetween(1, 5),
            'comment' => $this->faker->optional()->sentence(),
            'is_approved' => false,
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_approved' => true,
        ]);
    }
}
