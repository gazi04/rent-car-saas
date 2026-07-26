<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RateType;
use App\Models\PromoCode;
use App\Models\Vehicle;
use Carbon\CarbonInterface;

class PricingService
{
    /**
     * Calculate the price for a rental period. An optional promo code stacks on
     * top of the per-vehicle discount (applied to the post-vehicle-discount
     * amount); the combined figure is returned in `discount` and the total is
     * floored at zero.
     *
     * @return array{rate_type: RateType, subtotal: float, discount: float, total: float, deposit: float}
     */
    public function calculate(Vehicle $vehicle, CarbonInterface $start, CarbonInterface $end, ?PromoCode $promo = null): array
    {
        $hours = (int) ceil($start->diffInMinutes($end) / 60);
        $days = max(1, (int) ceil($hours / 24));

        [$rateType, $subtotal] = $this->selectRate($vehicle, $hours, $days);

        $vehicleDiscount = $this->applyDiscount($vehicle, $subtotal);
        $promoDiscount = $promo?->discountFor($subtotal - $vehicleDiscount) ?? 0.0;

        $discount = min($subtotal, $vehicleDiscount + $promoDiscount);
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
            return [RateType::Hourly, $hours * $vehicle->hourly_rate];
        }

        if ($days >= 30 && $vehicle->monthly_rate !== null) {
            return [RateType::Monthly, ceil($days / 30) * $vehicle->monthly_rate];
        }

        if ($days >= 7 && $vehicle->weekly_rate !== null) {
            return [RateType::Weekly, ceil($days / 7) * (float) $vehicle->weekly_rate];
        }

        return [RateType::Daily, $days * (float) $vehicle->daily_rate];
    }

    private function applyDiscount(Vehicle $vehicle, float $subtotal): float
    {
        $discount = match ($vehicle->discount_type) {
            'percentage' => $subtotal * ((float) $vehicle->discount_value / 100),
            'fixed' => (float) $vehicle->discount_value,
            default => 0.0,
        };

        return min($discount, $subtotal);
    }
}
