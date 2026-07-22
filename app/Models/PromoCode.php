<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A tenant-scoped discount code a customer enters at booking (backlog #8).
 * Validity is bounded by an optional date window, a global usage cap, and an
 * optional per-customer limit. Gated by PlanFeature::PromoCodes.
 *
 * @property string $type
 * @property string $value
 * @property int|null $max_uses
 * @property int $uses_count
 * @property int|null $per_customer_limit
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 */
#[Fillable(['tenant_id', 'code', 'type', 'value', 'starts_at', 'expires_at', 'max_uses', 'uses_count', 'per_customer_limit', 'is_active'])]
class PromoCode extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'per_customer_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The discount this code applies to $amount, capped so it never exceeds it.
     */
    public function discountFor(float $amount): float
    {
        $discount = match ($this->type) {
            'percentage' => $amount * ((float) $this->value / 100),
            'fixed' => (float) $this->value,
            default => 0.0,
        };

        return round(min(max($discount, 0.0), max($amount, 0.0)), 2);
    }

    public function withinWindow(): bool
    {
        $today = today();

        return ($this->starts_at === null || $this->starts_at->lte($today))
            && ($this->expires_at === null || $this->expires_at->gte($today));
    }

    /**
     * Live count of bookings that actually redeemed this code — the single
     * source of truth for the max_uses cap, instead of the mutable uses_count
     * column (which drifted permanently as pending/rejected bookings churned).
     */
    public function redeemedUsesCount(): int
    {
        return $this->bookings()->whereIn('status', BookingStatus::countsTowardPromoCap())->count();
    }

    public function hasUsesLeft(): bool
    {
        return $this->max_uses === null || $this->redeemedUsesCount() < $this->max_uses;
    }

    public function isCurrentlyValid(): bool
    {
        return $this->is_active && $this->withinWindow() && $this->hasUsesLeft();
    }
}
