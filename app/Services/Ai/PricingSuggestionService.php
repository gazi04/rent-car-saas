<?php

namespace App\Services\Ai;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Vehicle;

/**
 * Suggests a daily rate for one vehicle from its ~90-day booking history plus
 * same-category benchmarks across the tenant's fleet. Returns the suggestion +
 * reasoning; the operator confirms before anything touches the form, and the
 * form still has to be saved — the AI never writes a price directly.
 */
class PricingSuggestionService
{
    private const HISTORY_DAYS = 90;

    public function __construct(private readonly AiChatService $chat) {}

    /**
     * @return array{suggested_daily_rate: float, reasoning: string}
     */
    public function suggest(Vehicle $vehicle): array
    {
        /** @var array{suggested_daily_rate: float|int, reasoning: string} $result */
        $result = $this->chat->chat(
            messages: [
                [
                    'role' => 'system',
                    'content' => 'You advise a small car-rental company on pricing. Suggest a realistic daily rate in EUR '
                        .'based strictly on the data provided (recent demand, current rates, category benchmarks). '
                        .'Keep the reasoning to 2-3 sentences a non-analyst can follow.',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode($this->pricingData($vehicle), JSON_PRETTY_PRINT),
                ],
            ],
            jsonSchema: [
                'name' => 'pricing_suggestion',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'suggested_daily_rate' => ['type' => 'number'],
                        'reasoning' => ['type' => 'string'],
                    ],
                    'required' => ['suggested_daily_rate', 'reasoning'],
                    'additionalProperties' => false,
                ],
            ],
        );

        return [
            'suggested_daily_rate' => round((float) $result['suggested_daily_rate'], 2),
            'reasoning' => $result['reasoning'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pricingData(Vehicle $vehicle): array
    {
        $since = now()->subDays(self::HISTORY_DAYS);

        $vehicleBookings = Booking::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('end_date', '>=', $since)
            ->whereIn('status', [...BookingStatus::blocking(), BookingStatus::Completed]);

        $categoryVehicles = Vehicle::query()
            ->where('category', $vehicle->category)
            ->whereKeyNot($vehicle->id);

        return [
            'history_days' => self::HISTORY_DAYS,
            'vehicle' => [
                'category' => $vehicle->category->value,
                'year' => $vehicle->year,
                'current_daily_rate_eur' => (float) $vehicle->daily_rate,
                'current_weekly_rate_eur' => $vehicle->weekly_rate !== null ? (float) $vehicle->weekly_rate : null,
                'bookings_in_period' => (clone $vehicleBookings)->count(),
                'revenue_in_period_eur' => (float) (clone $vehicleBookings)->sum('total'),
                'booked_days_in_period' => (clone $vehicleBookings)->get()
                    ->sum(fn (Booking $booking): int => max(0, (int) ceil($booking->start_date->diffInHours($booking->end_date) / 24))),
            ],
            'same_category_fleet' => [
                'vehicle_count' => (clone $categoryVehicles)->count(),
                'average_daily_rate_eur' => round((float) (clone $categoryVehicles)->avg('daily_rate'), 2),
                'bookings_in_period' => Booking::query()
                    ->whereIn('vehicle_id', (clone $categoryVehicles)->pluck('id'))
                    ->where('end_date', '>=', $since)
                    ->count(),
            ],
        ];
    }
}
