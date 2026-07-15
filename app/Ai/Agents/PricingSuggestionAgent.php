<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\ReportsAiUsage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Suggests a daily rate (EUR) plus short reasoning for one vehicle. The pricing
 * data is passed as the prompt; structured output guarantees a numeric rate and
 * a reasoning string. Routed to the GitHub Models "github" provider (see config/ai.php).
 */
#[Provider('github')]
#[Model('openai/gpt-4.1')]
class PricingSuggestionAgent implements Agent, HasStructuredOutput, ReportsAiUsage
{
    use Promptable;

    public function aiFeature(): string
    {
        return 'pricing';
    }

    public function instructions(): string
    {
        return 'You advise a small car-rental company on pricing. Suggest a realistic daily rate in EUR '
            .'based strictly on the data provided (recent demand, current rates, category benchmarks). '
            .'Keep the reasoning to 2-3 sentences a non-analyst can follow.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suggested_daily_rate' => $schema->number()->required(),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
