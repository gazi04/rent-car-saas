<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
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
            'code' => strtoupper($this->faker->unique()->bothify('SAVE##??')),
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => null,
            'expires_at' => null,
            'max_uses' => null,
            'uses_count' => 0,
            'per_customer_limit' => null,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'fixed',
            'value' => $amount,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay()->startOfDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
