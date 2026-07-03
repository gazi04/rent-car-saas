<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\TenantPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manually recorded B2B payment (operator → platform, cash/bank transfer — no gateway).
 * Administered cross-tenant from the admin panel, so deliberately NOT tenant-scoped
 * (no BelongsToTenant), same as the Tenant model itself.
 *
 * @property PaymentMethod $method
 */
#[Fillable(['tenant_id', 'plan', 'method', 'amount', 'period_start', 'period_end', 'note', 'recorded_by'])]
class TenantPayment extends Model
{
    /** @use HasFactory<TenantPaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
