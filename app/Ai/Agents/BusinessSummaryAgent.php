<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Turns aggregated weekly booking numbers into 3-5 plain-language sentences for
 * the operator dashboard. Plain-text output (no schema). The summary language is
 * baked into the instructions, so it is passed in via the constructor. Routed to
 * the GitHub Models "github" provider (see config/ai.php).
 */
#[Provider('github')]
#[Model('openai/gpt-4.1')]
class BusinessSummaryAgent implements Agent
{
    use Promptable;

    public function __construct(public string $language) {}

    public function instructions(): string
    {
        return "You are a business analyst for a small car-rental company. Write 3-5 short sentences in {$this->language}, "
            .'plain language and no jargon, summarizing how the week went and what needs attention. '
            .'Base every statement strictly on the numbers provided; do not invent trends.';
    }
}
