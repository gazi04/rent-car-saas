<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `tenant_id` is intentionally omitted — the BelongsToTenant trait fills it
     * from the current tenant context. Create vehicles inside
     * `tenancy()->initialize($tenant)` (or pass `tenant_id` explicitly).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $daily = fake()->numberBetween(20, 120);

        return [
            'name' => fake()->randomElement(['Volkswagen Golf', 'Toyota Corolla', 'BMW 320i', 'Audi A4', 'Skoda Octavia', 'Mercedes C200', 'Ford Focus', 'Opel Astra']),
            'category' => fake()->randomElement(VehicleCategory::cases()),
            'year' => fake()->numberBetween(2015, 2025),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'transmission' => fake()->randomElement(Transmission::cases()),
            'seats' => fake()->randomElement([2, 4, 5, 7]),
            'daily_rate' => $daily,
            'hourly_rate' => round($daily / 8, 2),
            'weekly_rate' => $daily * 6,
            'monthly_rate' => $daily * 24,
            'discount_type' => null,
            'discount_value' => null,
            'mileage_limit' => fake()->randomElement([null, 150, 200, 300]),
            'deposit' => fake()->randomElement([null, 100, 200, 300]),
            'description' => fake()->optional()->sentence(),
            'custom_fields' => null,
            'status' => VehicleStatus::Available,
            'is_public' => true,
        ];
    }

    /**
     * A vehicle hidden from the public booking site.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }

    /**
     * A vehicle temporarily out of service.
     */
    public function underMaintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => VehicleStatus::UnderMaintenance,
        ]);
    }
}
