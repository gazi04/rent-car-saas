<?php

namespace App\Listeners;

use App\Ai\Contracts\ReportsAiUsage;
use App\Models\AiUsageLog;
use App\Services\Ai\AiCostEstimator;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Throwable;

/**
 * Records one AiUsageLog row per completed AI prompt. Listens on AgentPrompted,
 * which laravel/ai dispatches at the end of every ->prompt() call, so all three
 * AI features are captured without touching their service classes. Only agents
 * that opt in via ReportsAiUsage are logged.
 *
 * The event dispatches synchronously inside prompt(), so any error here would
 * bubble into a live operator-facing AI feature — the whole body is therefore
 * best-effort and swallows failures via report().
 *
 * Registered automatically via Laravel's listener discovery (the handle()
 * type-hint) — do NOT also Event::listen() it, or every call is logged twice.
 */
class RecordAiUsage
{
    public function __construct(private readonly AiCostEstimator $estimator) {}

    public function handle(AgentPrompted $event): void
    {
        try {
            $agent = $event->prompt->agent;
            $response = $event->response;

            // Streamed responses only finalize their usage after the stream is
            // consumed; the AI features here never stream, so skip them defensively.
            if (! $agent instanceof ReportsAiUsage || $response instanceof StreamedAgentResponse) {
                return;
            }

            $usage = $response->usage;
            $model = $response->meta->model ?? $event->prompt->model;

            AiUsageLog::query()->create([
                'tenant_id' => tenant()?->id,
                'feature' => $agent->aiFeature(),
                'provider' => $response->meta->provider ?? '',
                'model' => $model,
                'prompt_tokens' => $usage->promptTokens,
                'completion_tokens' => $usage->completionTokens,
                'reasoning_tokens' => $usage->reasoningTokens,
                'cache_read_input_tokens' => $usage->cacheReadInputTokens,
                'cache_write_input_tokens' => $usage->cacheWriteInputTokens,
                'total_tokens' => $usage->promptTokens + $usage->completionTokens,
                'estimated_cost' => $this->estimator->estimate($model, $usage),
                'created_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);
        }
    }
}
