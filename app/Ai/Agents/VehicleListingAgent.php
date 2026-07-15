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
 * Writes a public rental-listing description. The target language is carried in
 * the prompt text (not here), so the system instructions are language-agnostic.
 * Structured output guarantees a `description` string back. Routed to the GitHub
 * Models "github" provider (see config/ai.php).
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
            .'and never invent features that are not in the provided facts or visible in the photos.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()->required(),
        ];
    }
}
