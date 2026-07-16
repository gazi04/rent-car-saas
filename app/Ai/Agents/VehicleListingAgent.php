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
 * Writes a public rental-listing description in both English (`en`) and Albanian
 * (`sq`) from a single call, so the operator's storefront can show either to a
 * visitor without a second AI round-trip. Structured output guarantees both
 * strings back. Routed to the GitHub Models "github" provider (see config/ai.php).
 */
#[Provider('github')]
#[Model('openai/gpt-4.1')]
class VehicleListingAgent implements Agent, HasStructuredOutput, ReportsAiUsage
{
    use Promptable;

    public function aiFeature(): string
    {
        return 'listing';
    }

    public function instructions(): string
    {
        return 'You write short, appealing descriptions for a car-rental booking website. '
            .'2-3 sentences, plain text, no headings or bullet lists, no price, '
            .'and never invent features that are not in the provided facts or visible in the photos. '
            .'Return the same description in both English (the "en" field) and Albanian (the "sq" field).';
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
