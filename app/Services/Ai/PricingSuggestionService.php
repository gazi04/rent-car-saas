<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Ai\Agents\PricingSuggestionAgent;
use App\Enums\BookingStatus;
use App\Exceptions\AiRequestFailedException;
use App\Models\Booking;
use App\Models\Vehicle;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Suggests a daily rate for one vehicle from its ~90-day booking history plus
 * same-category benchmarks across the tenant's fleet. Returns the suggestion +
 * reasoning; the operator confirms before anything touches the form, and the
 * form still has to be saved — the AI never writes a price directly.
 */
class PricingSuggestionService
{
    private const int HISTORY_DAYS = 90;

    /**
     * @return array{suggested_daily_rate: float, reasoning: string}
     *
     * @throws AiRequestFailedException
     */
    public function suggest(Vehicle $vehicle, string $locale = 'en'): array
    {
        $language = $locale === 'sq' ? 'Albanian' : 'English';

        try {
            /** @var StructuredAgentResponse $response */
            $response = new PricingSuggestionAgent($language)->prompt(
                (string) json_encode($this->pricingData($vehicle), JSON_PRETTY_PRINT),
            );
        } catch (Throwable $throwable) {
            throw AiRequestFailedException::wrap($throwable);
        }

        /** @var array{suggested_daily_rate: float|int, reasoning: string} $result */
        $result = $response->toArray();

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
