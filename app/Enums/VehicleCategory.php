<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VehicleCategory: string implements HasLabel
{
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Van = 'van';
    case Hatchback = 'hatchback';
    case Pickup = 'pickup';
    case Luxury = 'luxury';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::Suv => 'SUV',
            self::Van => 'Van',
            self::Hatchback => 'Hatchback',
            self::Pickup => 'Pickup',
            self::Luxury => 'Luxury',
            self::Other => 'Other',
        };
    }
}
