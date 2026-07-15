<?php

namespace Database\Factories;

use App\Models\AiUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prompt = $this->faker->numberBetween(200, 4000);
        $completion = $this->faker->numberBetween(50, 1000);

        return [
            'tenant_id' => null,
            'feature' => $this->faker->randomElement(['listing', 'summary', 'pricing']),
            'provider' => 'openai',
            'model' => 'gpt-5-mini',
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'reasoning_tokens' => 0,
            'cache_read_input_tokens' => 0,
            'cache_write_input_tokens' => 0,
            'total_tokens' => $prompt + $completion,
            'estimated_cost' => 0,
            'created_at' => now(),
        ];
    }
}
