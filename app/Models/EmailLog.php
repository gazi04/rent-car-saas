<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmailStatus;
use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * One outbound email: recipient, subject, mailable, and delivery status.
 * Written as Sent by the LogSentEmail listener on MessageSent, then advanced
 * (delivered/bounced/complained) by the Resend webhook. Administered
 * cross-tenant from the admin panel, so deliberately NOT tenant-scoped (no
 * BelongsToTenant), same as AiUsageLog / TenantPayment / the Tenant model.
 * Unlike AiUsageLog these rows are mutable (the webhook updates status), so
 * they keep full timestamps.
 *
 * @property EmailStatus $status
 */
#[Fillable([
    'tenant_id', 'message_id', 'to_email', 'subject', 'mailable', 'status', 'error',
])]
class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory;

    use MassPrunable;

    /**
     * One row per outbound email means this table grows with send volume and
     * never shrinks on its own. Rows are an operational trail (did the renewal
     * reminder bounce?), not business records, so they age out.
     *
     * MassPrunable: no per-row events or files to clean up, so a single delete
     * query is enough. Driven by `model:prune` on the daily schedule.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where(
            'created_at',
            '<',
            now()->subDays(Config::integer('mail.log_retention_days')),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailStatus::class,
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
