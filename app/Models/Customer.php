<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A rent-a-car customer, auto-created/linked by phone whenever a booking is made
 * (docs/operator-feature-report.md #2). Tenant-scoped via BelongsToTenant — each
 * operator only ever sees their own customers. The blacklist flag is purely
 * informational; it never blocks a booking.
 *
 * @property bool $is_blacklisted
 */
#[Fillable(['name', 'phone', 'email', 'notes', 'is_blacklisted'])]
class Customer extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_blacklisted' => 'boolean',
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
     * Lifetime spend: only bookings that represent realised revenue (Active +
     * Completed), matching the revenue definition used on the Reports page.
     */
    public function totalSpend(): float
    {
        return (float) $this->bookings()
            ->whereIn('status', [BookingStatus::Active, BookingStatus::Completed])
            ->sum('total');
    }
}
