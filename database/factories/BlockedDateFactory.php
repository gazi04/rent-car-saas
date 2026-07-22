<?php

namespace Database\Factories;

use App\Models\BlockedDate;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

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
        $start = Date::instance(fake()->dateTimeBetween('now', '+7 days'));
        $end = $start->copy()->addDays(fake()->numberBetween(1, 7));

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
