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
        $hours = $this->rentalHours($start, $end);
        $days = $this->rentalDays($start, $end);

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

    /**
     * Whole days a rental spans, rounding any part-day up and flooring at one.
     * This is the app's single definition of rental length: the price is built
     * on it, and BookingService's max-duration guard measures against it, so a
     * booking can never be charged for more days than the guard allows.
     */
    public function rentalDays(CarbonInterface $start, CarbonInterface $end): int
    {
        return max(1, (int) ceil($this->rentalHours($start, $end) / 24));
    }

    /** Whole hours a rental spans, rounding any part-hour up. */
    private function rentalHours(CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) ceil($start->diffInMinutes($end) / 60);
    }

    /**
     * Compares every tier the vehicle has a rate for and charges whichever is
     * cheapest for this exact span, rather than picking one tier by a duration
     * threshold (deep-audit finding 03: threshold-only selection let a longer
     * booking cost less than a shorter one, since nothing ever compared a
     * chosen tier against the tier below it).
     *
     * @return array{0: RateType, 1: float}
     */
    private function selectRate(Vehicle $vehicle, int $hours, int $days): array
    {
        $candidates = [];

        // Each tier keeps its original eligibility threshold — weekly/monthly are
        // bulk-commitment rates the operator chose to require a minimum duration
        // for, not simply "whichever number is smaller" — but once a tier is
        // eligible, it now genuinely competes with the tiers below it instead of
        // being charged unconditionally. hourly_rate/monthly_rate resolve as
        // float already (no explicit @property override on Vehicle, unlike
        // daily_rate/weekly_rate below), so they are used as-is; casting them
        // again is a no-op PHPStan flags as redundant.
        if ($hours < 24 && $vehicle->hourly_rate !== null) {
            $candidates[] = [RateType::Hourly, $hours * $vehicle->hourly_rate];
        }

        // daily_rate is NOT NULL at the schema level — always a safe fallback.
        $candidates[] = [RateType::Daily, $days * (float) $vehicle->daily_rate];

        if ($days >= 7 && $vehicle->weekly_rate !== null) {
            $candidates[] = [RateType::Weekly, ceil($days / 7) * (float) $vehicle->weekly_rate];
        }

        if ($days >= 30 && $vehicle->monthly_rate !== null) {
            $candidates[] = [RateType::Monthly, ceil($days / 30) * $vehicle->monthly_rate];
        }

        // On an exact tie, prefer the larger billing unit — an arbitrary but
        // stable choice, since a tie means the customer pays the same either way.
        $priority = [RateType::Monthly, RateType::Weekly, RateType::Daily, RateType::Hourly];

        usort($candidates, function (array $a, array $b) use ($priority): int {
            $costComparison = $a[1] <=> $b[1];

            return $costComparison !== 0
                ? $costComparison
                : array_search($a[0], $priority, true) <=> array_search($b[0], $priority, true);
        });

        return $candidates[0];
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
