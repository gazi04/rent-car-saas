<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // tenant_id is intentionally omitted — BelongsToTenant fills it from the
        // active tenant context.
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('+3834#######'),
            'email' => fake()->optional(0.7)->safeEmail(),
            'notes' => fake()->optional(0.3)->sentence(),
            'is_blacklisted' => false,
        ];
    }

    public function blacklisted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_blacklisted' => true,
        ]);
    }
}
