<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property VehicleCategory $category
 * @property FuelType $fuel_type
 * @property Transmission $transmission
 * @property VehicleStatus $status
 * @property int $year
 * @property string $daily_rate
 * @property string|null $weekly_rate
 */
#[Fillable(['tenant_id', 'name', 'plate', 'category', 'year', 'fuel_type', 'transmission', 'seats', 'daily_rate', 'hourly_rate', 'weekly_rate', 'monthly_rate', 'discount_type', 'discount_value', 'mileage_limit', 'deposit', 'description', 'custom_fields', 'status', 'is_public'])]
class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<VehicleFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'available',
        'is_public' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => VehicleCategory::class,
            'fuel_type' => FuelType::class,
            'transmission' => Transmission::class,
            'status' => VehicleStatus::class,
            'custom_fields' => 'array',
            'description' => 'array',
            'is_public' => 'boolean',
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'weekly_rate' => 'decimal:2',
            'monthly_rate' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'deposit' => 'decimal:2',
        ];
    }

    /**
     * Resolve the vehicle description for a locale, falling back to English and
     * then to whatever language is stored. `description` holds a bilingual
     * {en, sq} payload; the public storefront picks by the visitor's locale.
     */
    public function descriptionFor(?string $locale = null): string
    {
        /** @var array<string, string> $content */
        $content = $this->description ?? [];
        $locale ??= app()->getLocale();

        return $content[$locale] ?? $content['en'] ?? (reset($content) ?: '');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vehicle_photos')->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('web')->format('webp')->quality(80);
        $this->addMediaConversion('thumb')->format('webp')->width(400);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<BlockedDate, $this> */
    public function blockedDates(): HasMany
    {
        return $this->hasMany(BlockedDate::class);
    }

    /** @return HasMany<ServiceRecord, $this> */
    public function serviceRecords(): HasMany
    {
        return $this->hasMany(ServiceRecord::class);
    }

    /** @return HasMany<WaitlistEntry, $this> */
    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<Review, $this> */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    /**
     * Average of this vehicle's approved star ratings, or null when it has none.
     */
    public function averageRating(): ?float
    {
        $average = $this->approvedReviews()->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }
}
