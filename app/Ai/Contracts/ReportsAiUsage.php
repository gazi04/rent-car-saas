<?php

declare(strict_types=1);

namespace App\Ai\Contracts;

/**
 * Opt-in marker for agents whose token usage/cost should be recorded. The
 * RecordAiUsage listener only logs prompts from agents implementing this, so
 * anonymous or non-app agents are ignored and the feature slug lives next to
 * the agent it describes.
 */
interface ReportsAiUsage
{
    /**
     * The feature slug stored on the usage row (e.g. listing|summary|pricing).
     */
    public function aiFeature(): string;
}
