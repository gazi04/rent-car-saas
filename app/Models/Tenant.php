<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

#[Fillable(['id', 'name', 'email', 'phone', 'status', 'plan', 'trial_ends_at'])]
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
            'trial_ends_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
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
     * Upsert a single setting. Keys not on the allow-list are silently ignored.
     */
    public function setSetting(string $key, mixed $value): void
    {
        if (! in_array($key, config('branding.keys'), true)) {
            return;
        }

        $normalized = ($value === '' || $value === null) ? null : (string) $value;

        $this->tenantSettings()->updateOrCreate(
            ['key' => $key],
            ['value' => $normalized],
        );

        $this->settingsCache = null;
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
