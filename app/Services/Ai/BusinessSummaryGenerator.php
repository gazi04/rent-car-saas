<?php

namespace App\Services\Ai;

use App\Ai\Agents\BusinessSummaryAgent;
use App\Enums\BookingStatus;
use App\Exceptions\AiRequestFailedException;
use App\Models\Booking;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Turns the last summary_period_days of a tenant's booking data into 3-5
 * plain-language sentences for the operator's dashboard. Runs inside tenant
 * context (all queries are BelongsToTenant-scoped) and sends the model
 * aggregated numbers only — never customer PII.
 */
class BusinessSummaryGenerator
{
    /**
     * @return array{content: array{en: string, sq: string}, period_start: CarbonInterface, period_end: CarbonInterface}
     *
     * @throws AiRequestFailedException
     */
    public function generate(): array
    {
        $days = (int) config('ai.summary_period_days');
        $periodStart = now()->subDays($days)->startOfDay();
        $periodEnd = now()->startOfDay();

        try {
            /** @var StructuredAgentResponse $response */
            $response = (new BusinessSummaryAgent)->prompt(
                (string) json_encode($this->metrics($days, $periodStart), JSON_PRETTY_PRINT),
            );
        } catch (Throwable $e) {
            throw AiRequestFailedException::wrap($e);
        }

        /** @var array{en?: string, sq?: string} $result */
        $result = $response->toArray();

        $en = trim((string) ($result['en'] ?? ''));
        $sq = trim((string) ($result['sq'] ?? ''));

        if ($en === '' || $sq === '') {
            throw AiRequestFailedException::malformedResponse();
        }

        return [
            'content' => ['en' => $en, 'sq' => $sq],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(int $days, CarbonInterface $periodStart): array
    {
        $previousStart = $periodStart->copy()->subDays($days);

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
