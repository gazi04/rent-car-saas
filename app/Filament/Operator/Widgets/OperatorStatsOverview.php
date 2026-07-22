<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top-of-dashboard KPI row for the operator's day: pickups/returns due today,
 * bookings awaiting confirmation, and overdue returns. All counts are tenant
 * -scoped automatically via BelongsToTenant on Booking. Not plan-gated —
 * baseline operational visibility for every operator.
 */
class OperatorStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -6;

    protected function getStats(): array
    {
        $startOfToday = today();
        $endOfToday = now()->endOfDay();

        $todaysPickups = Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereBetween('start_date', [$startOfToday, $endOfToday])
            ->count();

        $todaysReturns = Booking::query()
            ->where('status', BookingStatus::Active)
            ->whereBetween('end_date', [now(), $endOfToday])
            ->count();

        $awaiting = Booking::query()
            ->where('status', BookingStatus::Pending)
            ->count();

        $overdue = Booking::query()
            ->where('status', BookingStatus::Active)
            ->where('end_date', '<', now())
            ->count();

        return [
            Stat::make(__('panel.dashboard_todays_pickups'), $todaysPickups)
                ->color('info'),
            Stat::make(__('panel.dashboard_todays_returns'), $todaysReturns)
                ->color('info'),
            Stat::make(__('panel.dashboard_awaiting'), $awaiting)
                ->color($awaiting > 0 ? 'warning' : 'gray'),
            Stat::make(__('panel.dashboard_overdue'), $overdue)
                ->color($overdue > 0 ? 'danger' : 'gray'),
        ];
    }
}
