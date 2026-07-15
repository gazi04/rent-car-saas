<?php

namespace App\Filament\Widgets;

use App\Models\AiUsageLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platform-wide AI usage this month (§15.3). Cost reads €0 while the app runs on
 * the free GitHub Models provider (no price row); the call/token stats are the
 * live signal for spotting a runaway or abusive tenant even at zero cost.
 */
class AiUsageStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $thisMonth = AiUsageLog::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return [
            Stat::make('AI calls this month', (clone $thisMonth)->count())
                ->color('primary'),
            Stat::make('Tokens this month', number_format((int) (clone $thisMonth)->sum('total_tokens')))
                ->color('gray'),
            Stat::make('AI spend this month', '€'.number_format((float) (clone $thisMonth)->sum('estimated_cost'), 2))
                ->description('Estimated from token usage')
                ->color('success'),
        ];
    }
}
