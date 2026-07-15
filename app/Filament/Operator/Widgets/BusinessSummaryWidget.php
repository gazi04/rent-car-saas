<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\PlanFeature;
use App\Exceptions\AiRequestFailedException;
use App\Filament\Support\HelpAction;
use App\Models\AiBusinessSummary;
use App\Services\Ai\BusinessSummaryGenerator;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/**
 * Dashboard widget showing the tenant's most recent AI business summary
 * with a "Generate now" button that runs the summary
 * synchronously in the current tenant context. Gated behind the plan feature —
 * hidden entirely for tenants without it.
 */
class BusinessSummaryWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.operator.widgets.business-summary';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    public static function canView(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (tenant()?->allowsFeature(PlanFeature::AiBusinessSummary) ?? (bool) PlanFeature::AiBusinessSummary->default());
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
            || ! (tenant()?->allowsFeature(PlanFeature::AiBusinessSummary) ?? (bool) PlanFeature::AiBusinessSummary->default())) {
            Notification::make()->title(__('panel.unauthorized'))->danger()->send();

            return;
        }

        try {
            $summary = app(BusinessSummaryGenerator::class)->generate();
        } catch (AiRequestFailedException) {
            Notification::make()->title(__('panel.ai_error'))->danger()->send();

            return;
        }

        AiBusinessSummary::query()->updateOrCreate(
            [
                'period_start' => $summary['period_start'],
                'period_end' => $summary['period_end'],
            ],
            ['content' => $summary['content']],
        );

        Notification::make()->title(__('panel.ai_generated'))->success()->send();
    }
}
