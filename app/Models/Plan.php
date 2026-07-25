<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanFeature;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-defined subscription plan with per-feature toggles/limits
 * . Central model, NOT tenant-scoped — administered
 * cross-tenant from the admin panel, same as Tenant/TenantPayment. Tenants
 * reference plans by slug (tenants.plan string), so slugs are immutable after
 * creation; removal is archive-only (is_active = false) while referenced.
 *
 * @property array<string, mixed>|null $features
 */
#[Fillable(['name', 'slug', 'description', 'price', 'features', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /** The one plan slug the app branches on (trial-vs-paid reminder copy, extend-trial action). */
    public const string TRIAL_SLUG = 'trial';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function featureValue(PlanFeature $feature): mixed
    {
        return $this->features[$feature->value] ?? $feature->default();
    }

    /** Whether a Toggle-type feature is enabled on this plan. */
    public function allows(PlanFeature $feature): bool
    {
        return (bool) $this->featureValue($feature);
    }

    /** The cap for a Limit-type feature; null = unlimited. */
    public function limit(PlanFeature $feature): ?int
    {
        $value = $this->featureValue($feature);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan', 'slug');
    }

    /**
     * Options for admin plan pickers: active plans in display order, plus the
     * given slug when it points at an archived plan (so a tenant already on an
     * archived plan doesn't lose its current value in the form).
     *
     * @return array<string, string> slug => name
     */
    public static function options(?string $ensureSlug = null): array
    {
        $options = self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'slug')
            ->all();

        // Graceful degradation before PlanSeeder has run (fresh installs, most
        // tests): fall back to the historical hardcoded tiers.
        if ($options === []) {
            $options = [
                'trial' => 'Trial',
                'basic' => 'Basic',
                'standard' => 'Standard',
                'pro' => 'Pro',
            ];
        }

        if ($ensureSlug !== null && ! isset($options[$ensureSlug])) {
            $archived = self::query()->where('slug', $ensureSlug)->value('name');
            $options[$ensureSlug] = $archived ? $archived.' (archived)' : $ensureSlug;
        }

        return $options;
    }
}
