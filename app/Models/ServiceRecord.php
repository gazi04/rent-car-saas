<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ServiceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A tenant-scoped service history entry for a vehicle (operator feature #10).
 * Logging is free on every plan; `next_due_on` only drives a reminder/auto-block
 * when the tenant's plan enables PlanFeature::MaintenanceReminders.
 *
 * @property string $service_type
 * @property Carbon $performed_on
 * @property int|null $odometer
 * @property string|null $cost
 * @property Carbon|null $next_due_on
 * @property int|null $next_due_odometer
 * @property Carbon|null $reminder_sent_at
 * @property int|null $blocked_date_id
 */
#[Fillable(['vehicle_id', 'service_type', 'performed_on', 'odometer', 'cost', 'notes', 'next_due_on', 'next_due_odometer', 'reminder_sent_at', 'blocked_date_id'])]
class ServiceRecord extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ServiceRecordFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'odometer' => 'integer',
            'cost' => 'decimal:2',
            'next_due_on' => 'date',
            'next_due_odometer' => 'integer',
            'reminder_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<BlockedDate, $this> */
    public function blockedDate(): BelongsTo
    {
        return $this->belongsTo(BlockedDate::class);
    }
}
