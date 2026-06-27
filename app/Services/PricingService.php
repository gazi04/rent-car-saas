<?php

namespace App\Services;

use App\Enums\RateType;
use App\Models\Vehicle;
use Carbon\Carbon;

class PricingService
{
    /**
     * Calculate the price for a rental period.
     *
     * @return array{rate_type: RateType, subtotal: float, discount: float, total: float, deposit: float}
     */
    public function calculate(Vehicle $vehicle, Carbon $start, Carbon $end): array
    {
        $hours = (int) $start->diffInHours($end);
        $days = (int) ceil($hours / 24);

        [$rateType, $subtotal] = $this->selectRate($vehicle, $hours, $days);

        $discount = $this->applyDiscount($vehicle, $subtotal);
        $total = max(0, $subtotal - $discount);

        return [
            'rate_type' => $rateType,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'deposit' => (float) ($vehicle->deposit ?? 0),
        ];
    }

    /** @return array{0: RateType, 1: float} */
    private function selectRate(Vehicle $vehicle, int $hours, int $days): array
    {
        if ($hours < 24 && $vehicle->hourly_rate !== null) {
            return [RateType::Hourly, $hours * (float) $vehicle->hourly_rate];
        }

        if ($days >= 30 && $vehicle->monthly_rate !== null) {
            return [RateType::Monthly, ceil($days / 30) * (float) $vehicle->monthly_rate];
        }

        if ($days >= 7 && $vehicle->weekly_rate !== null) {
            return [RateType::Weekly, ceil($days / 7) * (float) $vehicle->weekly_rate];
        }

        return [RateType::Daily, $days * (float) $vehicle->daily_rate];
    }

    private function applyDiscount(Vehicle $vehicle, float $subtotal): float
    {
        return match ($vehicle->discount_type) {
            'percentage' => $subtotal * ((float) $vehicle->discount_value / 100),
            'fixed' => (float) $vehicle->discount_value,
            default => 0.0,
        };
    }
}
