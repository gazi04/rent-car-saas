<?php

namespace App\Services\Ai;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Vehicle;

/**
 * Turns the last summary_period_days of a tenant's booking data into 3-5
 * plain-language sentences for the operator's dashboard. Runs inside tenant
 * context (all queries are BelongsToTenant-scoped) and sends the model
 * aggregated numbers only — never customer PII.
 */
class BusinessSummaryGenerator
{
    public function __construct(private readonly AiChatService $chat) {}

    public function generate(string $locale): string
    {
        $language = $locale === 'sq' ? 'Albanian' : 'English';

        return $this->chat->chat(messages: [
            [
                'role' => 'system',
                'content' => "You are a business analyst for a small car-rental company. Write 3-5 short sentences in {$language}, "
                    .'plain language and no jargon, summarizing how the week went and what needs attention. '
                    .'Base every statement strictly on the numbers provided; do not invent trends.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($this->metrics(), JSON_PRETTY_PRINT),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $days = (int) config('ai.summary_period_days');
        $periodStart = now()->subDays($days)->startOfDay();
        $previousStart = now()->subDays($days * 2)->startOfDay();

        $inPeriod = fn ($start, $end) => Booking::query()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);

        $revenue = fn ($start, $end) => (float) $inPeriod($start, $end)
            ->whereIn('status', [BookingStatus::Active, BookingStatus::Completed])
            ->sum('total');

        return [
            'period_days' => $days,
            'bookings_this_period' => $inPeriod($periodStart, now())->count(),
            'bookings_previous_period' => $inPeriod($previousStart, $periodStart)->count(),
            'bookings_by_status' => $inPeriod($periodStart, now())
                ->get()
                ->countBy(fn (Booking $booking): string => $booking->status->value)
                ->all(),
            'revenue_this_period_eur' => $revenue($periodStart, now()),
            'revenue_previous_period_eur' => $revenue($previousStart, $periodStart),
            'awaiting_confirmation' => Booking::query()->where('status', BookingStatus::Pending)->count(),
            'overdue_returns' => Booking::query()
                ->where('status', BookingStatus::Active)
                ->where('end_date', '<', now())
                ->count(),
            'fleet_size' => Vehicle::query()->count(),
            'vehicles_with_no_bookings_this_period' => Vehicle::query()
                ->whereDoesntHave('bookings', function ($query) use ($periodStart): void {
                    $query->where('start_date', '<=', now())
                        ->where('end_date', '>=', $periodStart)
                        ->whereIn('status', BookingStatus::blocking());
                })
                ->count(),
        ];
    }
}
