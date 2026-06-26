<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantStats extends StatsOverviewWidget
{
    /**
     * Counts only for now. Real MRR / revenue arrives with the Billing step.
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Total operators', Tenant::count())
                ->color('primary'),
            Stat::make('Active', Tenant::where('status', 'active')->count())
                ->color('success'),
            Stat::make('Pending approval', Tenant::where('status', 'pending')->count())
                ->color('warning'),
            Stat::make('On trial', Tenant::where('plan', 'trial')->count())
                ->color('gray'),
        ];
    }
}
