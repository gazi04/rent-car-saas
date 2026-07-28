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
#[Fillable(['name', 'slug', 'description', 'price', 'features', 'is_active', 'is_trial', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * Fallback trial slug, used only when no plan row is flagged is_trial
     * (fresh installs before PlanSeeder runs, and most test factories, which
     * don't create a backing Plan row). Source of truth is Plan::trialSlug().
     */
    public const string TRIAL_SLUG = 'trial';

    /** Memoized per-request; invalidated whenever a Plan is saved or deleted. */
    private static ?string $trialSlugCache = null;

    protected static function booted(): void
    {
        static::saved(function (self $plan): void {
            self::$trialSlugCache = null;

            if ($plan->is_trial) {
                // Enforce "only one trial plan": unflag every other row in a
                // single UPDATE. Deliberately bypasses Eloquent events on
                // those rows — this is bookkeeping, not a change any
                // observer should react to.
                self::query()->whereKeyNot($plan->id)->where('is_trial', true)->update(['is_trial' => false]);
            }
        });

        static::deleted(function (): void {
            self::$trialSlugCache = null;
        });
    }

    /**
     * The slug currently flagged as the trial tier. Falls back to
     * TRIAL_SLUG when no plan is flagged — the reason isOnTrial() keeps
     * working unchanged in suites that create tenants with no backing Plan
     * row.
     */
    public static function trialSlug(): string
    {
        if (self::$trialSlugCache !== null) {
            return self::$trialSlugCache;
        }

        $slug = self::query()->where('is_trial', true)->value('slug');

        return self::$trialSlugCache = is_string($slug) ? $slug : self::TRIAL_SLUG;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_trial' => 'boolean',
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
            $options[$ensureSlug] = is_string($archived) && $archived !== '' ? $archived.' (archived)' : $ensureSlug;
        }

        return $options;
    }
}
