<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Models\TenantPayment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        // Money actually collected this calendar month. Not a forward MRR projection;
        // there is no recurring-charge concept to project from.
        $revenueThisMonth = TenantPayment::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        return [
            Stat::make('Total operators', Tenant::count())
                ->color('primary'),
            Stat::make('Active', Tenant::where('status', 'active')->count())
                ->color('success'),
            Stat::make('Pending approval', Tenant::where('status', 'pending')->count())
                ->color('warning'),
            Stat::make('On trial', Tenant::where('plan', 'trial')->count())
                ->color('gray'),
            Stat::make('Revenue this month', '€'.number_format((float) $revenueThisMonth, 2))
                ->description('Payments recorded this month')
                ->color('success'),
        ];
    }
}
