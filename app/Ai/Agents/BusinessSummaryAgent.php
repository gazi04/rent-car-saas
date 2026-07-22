<?php

declare(strict_types=1);

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
 * Turns aggregated weekly booking numbers into 3-5 plain-language sentences for
 * the operator dashboard. Structured output returns the same summary in both
 * English (`en`) and Albanian (`sq`) from a single call, so the dashboard can
 * follow the operator's current UI language without regenerating. Routed to the
 * GitHub Models "github" provider (see config/ai.php).
 */
#[Provider('github')]
#[Model('openai/gpt-4.1')]
class BusinessSummaryAgent implements Agent, HasStructuredOutput, ReportsAiUsage
{
    use Promptable;

    public function aiFeature(): string
    {
        return 'summary';
    }

    public function instructions(): string
    {
        return 'You are a business analyst for a small car-rental company. Write 3-5 short sentences, '
            .'plain language and no jargon, summarizing how the week went and what needs attention. '
            .'Base every statement strictly on the numbers provided; do not invent trends. '
            .'Return the same summary in both English (the "en" field) and Albanian (the "sq" field).';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'en' => $schema->string()->required(),
            'sq' => $schema->string()->required(),
        ];
    }
}
