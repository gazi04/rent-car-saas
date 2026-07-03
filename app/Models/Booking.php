<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RateType;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property BookingStatus $status
 * @property RateType $rate_type
 */
#[Fillable(['vehicle_id', 'customer_id', 'reference', 'customer_name', 'customer_phone', 'customer_email', 'pickup_location', 'notes', 'start_date', 'end_date', 'rate_type', 'subtotal', 'discount_amount', 'total', 'deposit', 'status', 'locale', 'started_at', 'completed_at', 'start_odometer', 'end_odometer'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'rate_type' => RateType::class,
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasOne<Contract, $this> */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }
}
