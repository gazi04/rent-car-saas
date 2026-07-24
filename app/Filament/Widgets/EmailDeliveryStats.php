<?php

namespace App\Filament\Widgets;

use App\Enums\EmailStatus;
use App\Models\EmailLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Platform-wide email delivery health this month (§15.7). Bounced is the signal
 * that matters — a bouncing renewal reminder or booking email is otherwise
 * silent. Status is populated by the Resend webhook; without it configured,
 * rows stay 'sent' and only the first stat moves.
 */
class EmailDeliveryStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $thisMonth = EmailLog::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);

        $sent = (clone $thisMonth)->count();
        $delivered = (clone $thisMonth)->where('status', EmailStatus::Delivered->value)->count();
        $bounced = (clone $thisMonth)->where('status', EmailStatus::Bounced->value)->count();
        $complained = (clone $thisMonth)->where('status', EmailStatus::Complained->value)->count();

        $bounceRate = $sent > 0 ? round($bounced / $sent * 100, 1) : 0.0;

        return [
            Stat::make('Emails this month', number_format($sent))
                ->color('primary'),
            Stat::make('Delivered', number_format($delivered))
                ->color('success'),
            Stat::make('Bounced', number_format($bounced))
                ->description($bounceRate.'% of sent')
                ->color($bounced > 0 ? 'danger' : 'gray'),
            Stat::make('Complaints', number_format($complained))
                ->color($complained > 0 ? 'warning' : 'gray'),
        ];
    }
}
