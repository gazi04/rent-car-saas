<?php

namespace App\Models;

use App\Enums\PlanFeature;
use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property TenantStatus $status
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $paid_until
 */
#[Fillable(['id', 'name', 'email', 'phone', 'status', 'plan', 'trial_ends_at', 'paid_until'])]
#[Hidden(['stripe_id', 'stripe_subscription_id'])]
class Tenant extends BaseTenant implements HasMedia
{
    /** @use HasFactory<TenantFactory> */
    use HasDomains, HasFactory, InteractsWithMedia;

    /** @var array<string, mixed>|null */
    private ?array $settingsCache = null;

    /**
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'email',
            'phone',
            'status',
            'plan',
            'trial_ends_at',
            'paid_until',
            'stripe_id',
            'stripe_subscription_id',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'paid_until' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public function isOnTrial(): bool
    {
        return $this->plan === Plan::TRIAL_SLUG;
    }

    /**
     * The plan row behind the tenant's plan slug. Named subscriptionPlan to
     * avoid colliding with the plan string attribute.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan', 'slug');
    }

    /**
     * Whether this tenant's plan enables a Toggle-type feature. No matching
     * plan row (unseeded slug, tests, legacy data) = the permissive default.
     */
    public function allowsFeature(PlanFeature $feature): bool
    {
        return $this->subscriptionPlan?->allows($feature) ?? (bool) $feature->default();
    }

    /**
     * The cap this tenant's plan sets for a Limit-type feature; null = unlimited
     * (including when no plan row exists).
     */
    public function featureLimit(PlanFeature $feature): ?int
    {
        return $this->subscriptionPlan?->limit($feature);
    }

    /**
     * @return HasMany<TenantPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TenantPayment::class, 'tenant_id');
    }

    /**
     * @return HasMany<TenantSetting, $this>
     */
    public function tenantSettings(): HasMany
    {
        return $this->hasMany(TenantSetting::class, 'tenant_id');
    }

    /**
     * Returns all settings as a key→value map, cached for the request lifetime.
     *
     * @return array<string, string|null>
     */
    public function settings(): array
    {
        return $this->settingsCache ??= $this->tenantSettings()->pluck('value', 'key')->all();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $value = $this->settings()[$key] ?? null;

        return $value !== null ? $value : $default;
    }

    /**
     * A content setting in the current locale. Falls back to the legacy
     * un-suffixed key (values saved before the bilingual split), then to the
     * other language (some content beats none), then to $default.
     */
    public function localizedSetting(string $key, mixed $default = null): mixed
    {
        $locale = app()->getLocale();
        $other = $locale === 'sq' ? 'en' : 'sq';

        return $this->setting("{$key}_{$locale}")
            ?? $this->setting($key)
            ?? $this->setting("{$key}_{$other}")
            ?? $default;
    }

    /**
     * Upsert a single setting. Keys not on the allow-list are silently
     * ignored, as are values that fail the per-key format guard below.
     */
    public function setSetting(string $key, mixed $value): void
    {
        // Allow-list spans branding keys + custom-template keys; anything else
        // is silently rejected (prevents mass-assignment of arbitrary settings).
        if (! in_array($key, [...config('branding.keys'), ...config('templates.keys')], true)) {
            return;
        }

        $normalized = ($value === '' || $value === null) ? null : (string) $value;

        if ($normalized !== null && ! $this->settingValueIsValid($key, $normalized)) {
            return;
        }

        $this->tenantSettings()->updateOrCreate(
            ['key' => $key],
            ['value' => $normalized],
        );

        $this->settingsCache = null;
    }

    /**
     * Per-key value-format guard for settings that reach an unescaped
     * render sink (CSS custom properties) or a curated allow-list. Keys
     * not listed here have no extra format constraint beyond the key
     * allow-list above — their values are HTML-escaped at render, not
     * interpolated raw.
     *
     * Rejects silently rather than throwing, mirroring the invalid-key
     * branch above: the Filament form's own rules/allow-lists already keep
     * a bad value from reaching this method on the normal path, so this
     * only ever fires on an off-form write (tinker, a seeder, a future
     * import job).
     */
    private function settingValueIsValid(string $key, string $value): bool
    {
        if (in_array($key, ['color_primary', 'color_secondary'], true)) {
            return (bool) preg_match((string) config('branding.color_format'), $value);
        }

        if ($key === 'font_family') {
            return array_key_exists($value, config('branding.fonts', []));
        }

        if ($key === 'default_locale') {
            return in_array($value, ['sq', 'en'], true);
        }

        if (in_array($key, ['social_facebook', 'social_instagram'], true)) {
            $scheme = parse_url($value, PHP_URL_SCHEME);

            return in_array($scheme, ['http', 'https'], true) && filter_var($value, FILTER_VALIDATE_URL) !== false;
        }

        return true;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->useDisk('public')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->format('webp')->width(400);
    }

    public function logoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('logo', 'thumb');

        return $url !== '' ? $url : null;
    }

    /**
     * Absolute logo URL for email clients — unlike a browser rendering the
     * public site, a mail client has no page origin to resolve a relative
     * URL against.
     */
    public function logoUrlForEmail(): ?string
    {
        $url = $this->logoUrl();

        return $url !== null ? rtrim(config('app.url'), '/').$url : null;
    }

    public function colorPrimary(): string
    {
        return $this->validatedColor('color_primary', (string) config('branding.defaults.color_primary', '#2563eb'));
    }

    public function colorSecondary(): string
    {
        return $this->validatedColor('color_secondary', (string) config('branding.defaults.color_secondary', '#1e40af'));
    }

    /**
     * A stored color setting, re-validated against the hex format at read
     * time so a row written before this format check existed — or via any
     * future bypass of setSetting() — can never reach the public <style>
     * block or an email's inline style attribute.
     */
    private function validatedColor(string $key, string $default): string
    {
        $value = (string) $this->setting($key);

        return preg_match((string) config('branding.color_format'), $value) === 1 ? $value : $default;
    }

    public function socialFacebookUrl(): ?string
    {
        return $this->validatedSocialUrl('social_facebook');
    }

    public function socialInstagramUrl(): ?string
    {
        return $this->validatedSocialUrl('social_instagram');
    }

    /**
     * A stored social-link setting, re-validated against the http/https
     * scheme guard at read time so a row written before this check existed
     * — or via any future bypass of setSetting() — can never render as a
     * javascript: (or other dangerous-scheme) href on the public footer.
     */
    private function validatedSocialUrl(string $key): ?string
    {
        $value = $this->setting($key);

        return is_string($value) && $this->settingValueIsValid($key, $value) ? $value : null;
    }

    /**
     * The tenant has no locale of its own — use its operator's saved panel
     * language, falling back to the platform's primary market (sq). Drives
     * the subscription-reminder emails and the AI business summary.
     */
    public function operatorLocale(): string
    {
        return User::query()->where('tenant_id', $this->id)->value('locale') ?? 'sq';
    }

    /**
     * The public origin of this tenant's storefront (scheme + subdomain [+ port]).
     *
     * Queue workers have no HTTP request, so signed URLs must be generated against
     * this root (via URL::forceRootUrl) for the signature to validate when the
     * customer opens the link on the tenant subdomain. Scheme and port come from
     * app.url — that config MUST match the real public origin (https behind a
     * TLS-terminating proxy), or every signed link 403s.
     */
    public function publicRootUrl(): ?string
    {
        /** @var string|null $domain */
        $domain = $this->domains()->first()?->domain;

        if ($domain === null) {
            return null;
        }

        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);

        return $scheme.'://'.$domain.($port ? ':'.$port : '');
    }
}
