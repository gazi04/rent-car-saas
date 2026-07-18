<?php

namespace Database\Factories;

use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
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
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => null,
            'start_date' => now()->addDays(10)->startOfDay(),
            'end_date' => now()->addDays(13)->startOfDay(),
            'locale' => 'en',
            'notified_at' => null,
        ];
    }

    public function forDates(string $start, string $end): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }

    public function notified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'notified_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'start_date' => now()->subDays(3)->startOfDay(),
            'end_date' => now()->subDay()->startOfDay(),
        ]);
    }
}
