<?php

namespace App\Services\Ai;

use Laravel\Ai\Responses\Data\Usage;

/**
 * Computes the estimated EUR cost of one AI call from its token usage and the
 * per-model price table in config('ai.pricing'). Providers return token counts
 * only, never a price, so cost is derived app-side. An unknown model (no price
 * row) yields 0 — the usage tokens are still recorded elsewhere.
 */
class AiCostEstimator
{
    public function estimate(string $model, Usage $usage): float
    {
        /** @var array{input?: float|int, cached_input?: float|int|null, output?: float|int}|null $prices */
        $prices = config("ai.pricing.{$model}");

        if ($prices === null) {
            return 0.0;
        }

        $input = (float) ($prices['input'] ?? 0);
        $output = (float) ($prices['output'] ?? 0);
        $cachedInput = isset($prices['cached_input']) ? (float) $prices['cached_input'] : $input;

        $nonCachedInput = max(0, $usage->promptTokens - $usage->cacheReadInputTokens);

        return $nonCachedInput / 1_000_000 * $input
            + $usage->cacheReadInputTokens / 1_000_000 * $cachedInput
            + ($usage->completionTokens + $usage->reasoningTokens) / 1_000_000 * $output;
    }
}
