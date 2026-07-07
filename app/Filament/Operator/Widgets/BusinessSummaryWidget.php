<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\PlanFeature;
use App\Exceptions\AiRequestFailedException;
use App\Filament\Support\HelpAction;
use App\Models\AiBusinessSummary;
use App\Services\Ai\BusinessSummaryGenerator;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/**
 * Dashboard widget showing the tenant's most recent AI business summary
 * with a "Generate now" button that runs the summary
 * synchronously in the current tenant context. Gated behind the plan feature —
 * hidden entirely for tenants without it.
 */
class BusinessSummaryWidget extends Widget
{
    protected string $view = 'filament.operator.widgets.business-summary';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (tenant()?->allowsFeature(PlanFeature::AiBusinessSummary) ?? false);
    }

    public function latestSummary(): ?AiBusinessSummary
    {
        return AiBusinessSummary::query()->latest()->first();
    }

    public function helpAction(): Action
    {
        return HelpAction::make('business_summary');
    }

    public function generate(): void
    {
        if (! (auth()->user()?->isOwner() ?? false)
            || ! (tenant()?->allowsFeature(PlanFeature::AiBusinessSummary) ?? false)) {
            Notification::make()->title(__('panel.unauthorized'))->danger()->send();

            return;
        }

        $days = (int) config('ai.summary_period_days');

        try {
            $content = app(BusinessSummaryGenerator::class)->generate(app()->getLocale());
        } catch (AiRequestFailedException) {
            Notification::make()->title(__('panel.ai_error'))->danger()->send();

            return;
        }

        AiBusinessSummary::query()->create([
            'content' => $content,
            'period_start' => now()->subDays($days)->startOfDay(),
            'period_end' => now()->startOfDay(),
        ]);

        Notification::make()->title(__('panel.ai_generated'))->success()->send();
    }
}
