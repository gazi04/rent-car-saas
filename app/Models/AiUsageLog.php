<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded AI call: token usage + estimated € cost, attributed to a tenant
 * and feature. Written from the RecordAiUsage listener. Administered cross-tenant
 * from the admin panel, so deliberately NOT tenant-scoped (no BelongsToTenant),
 * same as TenantPayment and the Tenant model itself. Rows are immutable —
 * created_at only, no updated_at.
 */
#[Fillable([
    'tenant_id', 'feature', 'provider', 'model',
    'prompt_tokens', 'completion_tokens', 'reasoning_tokens',
    'cache_read_input_tokens', 'cache_write_input_tokens', 'total_tokens',
    'estimated_cost', 'created_at',
])]
#[WithoutTimestamps]
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_cost' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
