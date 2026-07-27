<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Computes the estimated EUR cost of one AI call from its token usage and the
 * per-model price table in config('ai.pricing'). Providers return token counts
 * only, never a price, so cost is derived app-side. An unknown model (no price
 * row) yields 0 — the usage tokens are still recorded elsewhere.
 */
class AiCostEstimator
{
    /**
     * Models already reported as unpriced, so a busy queue logs once per model
     * per process instead of once per call.
     *
     * @var array<string, true>
     */
    private static array $warnedModels = [];

    public function estimate(string $model, Usage $usage): float
    {
        /** @var array{input?: float|int, cached_input?: float|int|null, output?: float|int}|null $prices */
        $prices = config('ai.pricing.'.$model);

        if ($prices === null) {
            // €0 is correct while the app runs on the free provider, and wrong
            // the moment a paid model is wired without a price row — at which
            // point every cost figure in the admin panel silently under-reports.
            // Say so rather than letting the zero pass for a real number.
            if (! isset(self::$warnedModels[$model])) {
                self::$warnedModels[$model] = true;

                Log::warning('AI usage recorded with no price row; cost logged as 0.', [
                    'model' => $model,
                    'hint' => "Add a 'pricing.{$model}' entry to config/ai.php once this model is paid.",
                ]);
            }

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
