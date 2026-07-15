<?php

namespace Database\Factories;

use App\Models\AiBusinessSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBusinessSummary>
 */
class AiBusinessSummaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = now()->subDays(7)->startOfDay();

        return [
            'content' => ['en' => $this->faker->paragraph(), 'sq' => $this->faker->paragraph()],
            'period_start' => $periodStart,
            'period_end' => now()->startOfDay(),
        ];
    }
}
