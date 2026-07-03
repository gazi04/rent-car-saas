<?php

namespace App\Models;

use Database\Factories\AiBusinessSummaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * A stored weekly AI business summary. Operator-facing,
 * so it is tenant-scoped via BelongsToTenant — unlike the central TenantPayment,
 * every read/write happens inside the operator's tenant context and only ever
 * sees that tenant's rows.
 */
#[Fillable(['tenant_id', 'content', 'period_start', 'period_end'])]
class AiBusinessSummary extends Model
{
    /** @use HasFactory<AiBusinessSummaryFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }
}
