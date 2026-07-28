<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Plan';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([15, 29, 49]),
            'features' => [],
            'is_active' => true,
            'is_trial' => false,
            'sort_order' => 0,
        ];
    }

    /** @param array<string, mixed> $features */
    public function withFeatures(array $features): static
    {
        return $this->state(fn (): array => ['features' => $features]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function trial(): static
    {
        return $this->state(fn (): array => ['is_trial' => true]);
    }
}
