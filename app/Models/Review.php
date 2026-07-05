<?php

namespace App\Models;

use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A star rating + comment a customer leaves after a completed rental, submitted through a tokenless signed link. Tenant-scoped via
 * BelongsToTenant. Created unapproved; only approved reviews render publicly,
 * giving the operator a moderation gate.
 *
 * @property int $rating
 * @property bool $is_approved
 * @property Carbon $submitted_at
 */
#[Fillable(['tenant_id', 'booking_id', 'vehicle_id', 'customer_id', 'reviewer_name', 'rating', 'comment', 'is_approved', 'submitted_at'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_approved' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
