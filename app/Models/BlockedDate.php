<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BlockedDateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 */
#[Fillable(['vehicle_id', 'start_date', 'end_date', 'reason'])]
class BlockedDate extends Model
{
    /** @use HasFactory<BlockedDateFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
