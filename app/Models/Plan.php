<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanFeature;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
 * @property array{en?: string, sq?: string}|null $marketing_description
 * @property list<string>|null $marketing_highlights
 * @property bool $is_public
 */
#[Fillable(['name', 'slug', 'description', 'price', 'features', 'is_active', 'is_trial', 'sort_order', 'is_public', 'marketing_description', 'marketing_highlights'])]
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

    /** Public pricing cards shown on the marketing homepage; matches the `lg:grid-cols-4` grid. */
    public const int MARKETING_MAX = 4;

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
            'is_public' => 'boolean',
            'marketing_description' => 'array',
            'marketing_highlights' => 'array',
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

    /**
     * The admin-curated bullet lines for this plan's public pricing card.
     *
     * `marketing_highlights` holds the PlanFeature values the admin ticked in the
     * panel; each is rendered through PlanFeature::marketingLine() (already
     * localized) and ordered by PlanFeature::marketingOrder() regardless of the
     * order they were picked. A pick whose line resolves to null (e.g. a toggle
     * that is off on this plan) is silently dropped.
     *
     * @return list<string>
     */
    public function marketingHighlightLines(): array
    {
        $chosen = $this->marketing_highlights ?? [];

        $lines = [];

        foreach (PlanFeature::marketingOrder() as $feature) {
            if (! in_array($feature->value, $chosen, true)) {
                continue;
            }

            $line = $feature->marketingLine($this);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** The one-line pricing-card tagline for the active locale, falling back to English. */
    public function marketingTagline(): ?string
    {
        $copy = $this->marketing_description ?? [];

        return $copy[app()->getLocale()] ?? $copy['en'] ?? null;
    }

    /** @return HasMany<Tenant, $this> */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'plan', 'slug');
    }

    /**
     * Active plans in display order — the single definition of "the plans".
     *
     * Used by the public pricing table (via the view composer in
     * AppServiceProvider) and by options() below, so the marketing page and the
     * admin pickers cannot disagree about which plans exist or in what order.
     *
     * @return Collection<int, self>
     */
    public static function activeInDisplayOrder(): Collection
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * The plans shown on the public marketing homepage: active, flagged public,
     * cheapest first, capped to the pricing grid. Deliberately narrower than
     * activeInDisplayOrder() — a private/custom plan (is_public = false) stays
     * assignable and billable via options() but never appears on the homepage.
     *
     * @return Collection<int, self>
     */
    public static function publiclyListed(): Collection
    {
        return self::activeInDisplayOrder()
            ->where('is_public', true)
            ->take(self::MARKETING_MAX)
            ->values();
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
        $options = self::activeInDisplayOrder()
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
