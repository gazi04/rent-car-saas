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

#[Fillable(['tenant_id', 'name', 'category', 'year', 'fuel_type', 'transmission', 'seats', 'daily_rate', 'hourly_rate', 'weekly_rate', 'monthly_rate', 'discount_type', 'discount_value', 'mileage_limit', 'deposit', 'description', 'custom_fields', 'status', 'is_public'])]
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
            'is_public' => 'boolean',
            'daily_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'weekly_rate' => 'decimal:2',
            'monthly_rate' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'deposit' => 'decimal:2',
        ];
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
}
