<?php

namespace Database\Factories;

use App\Models\ServiceRecord;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRecord>
 */
class ServiceRecordFactory extends Factory
{
    /**
     * `tenant_id` is intentionally omitted — BelongsToTenant fills it from context.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'service_type' => fake()->randomElement(config('maintenance.service_types')),
            'performed_on' => now()->subMonth()->startOfDay(),
            'odometer' => fake()->numberBetween(10000, 100000),
            'cost' => fake()->randomFloat(2, 20, 300),
            'notes' => null,
            'next_due_on' => null,
            'next_due_odometer' => null,
            'reminder_sent_at' => null,
            'blocked_date_id' => null,
        ];
    }

    /** Due within the reminder window (soonest configured day), not yet reminded. */
    public function due(): static
    {
        /** @var array<int, int> $reminderDays */
        $reminderDays = config('maintenance.reminder_days');
        $soonestDay = collect($reminderDays)->min();

        return $this->state(fn (array $attributes): array => [
            'next_due_on' => now()->addDays($soonestDay)->startOfDay(),
        ]);
    }

    /** Past due — should be auto-blocked by the sweep. */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_due_on' => now()->subDay()->startOfDay(),
        ]);
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn (array $attributes): array => [
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
