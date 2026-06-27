<?php

namespace Database\Factories;

use App\Models\BlockedDate;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedDate>
 */
class BlockedDateFactory extends Factory
{
    /**
     * `tenant_id` is intentionally omitted — BelongsToTenant fills it from context.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+14 days');
        $end = fake()->dateTimeBetween($start, '+7 days');

        return [
            'vehicle_id' => Vehicle::factory(),
            'start_date' => $start,
            'end_date' => $end,
            'reason' => fake()->randomElement(['maintenance', 'personal', 'other', null]),
        ];
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (array $attributes): array => [
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
