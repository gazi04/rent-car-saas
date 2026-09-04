<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * Someone waiting for a vehicle whose dates are taken (backlog #2). Created from
 * the public "notify me" panel — deliberately NOT linked to a Customer: customers
 * are keyed by a unique phone, a waitlist only has an email, and an unauthenticated
 * form must not write into the operator's CRM. A Customer is created normally by
 * BookingService::resolveCustomer() if the person converts.
 *
 * Gated by PlanFeature::Waitlist.
 *
 * Null start_date/end_date mean "any availability" — the shape the stock-alert
 * idea needs. Nothing dispatches on that condition yet.
 *
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $locale
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 */
#[Fillable(['vehicle_id', 'name', 'email', 'phone', 'start_date', 'end_date', 'locale', 'notified_at'])]
class WaitlistEntry extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Still waiting: never mailed, and its dates haven't passed. */
    public function isPending(): bool
    {
        return $this->notified_at === null && ! $this->hasExpired();
    }

    /**
     * A dateless entry never expires — it waits for the vehicle itself, not a
     * window.
     */
    public function hasExpired(): bool
    {
        return $this->start_date !== null && $this->start_date->lt(today());
    }

    /**
     * Half-open overlap, matching AvailabilityService: touching ends don't
     * conflict. A dateless entry overlaps everything — it wants any slot.
     *
     * CarbonInterface, not Carbon: the compared range comes from Booking dates
     * (CarbonImmutable) as well as from other entries (mutable Carbon). These are
     * only read, never mutated.
     */
    public function overlaps(?CarbonInterface $start, ?CarbonInterface $end): bool
    {
        if ($this->start_date === null || $this->end_date === null || ! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return true;
        }

        return $this->start_date->lt($end) && $this->end_date->gt($start);
    }
}
