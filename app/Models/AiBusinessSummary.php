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
            'content' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    /**
     * Resolve the summary text for a locale, falling back to English and then to
     * whatever language was stored. `content` holds a bilingual {en, sq} payload.
     */
    public function contentFor(?string $locale = null): string
    {
        /** @var array<string, string> $content */
        $content = $this->content ?? [];
        $locale ??= app()->getLocale();

        return $content[$locale] ?? $content['en'] ?? (reset($content) ?: '');
    }
}
