<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FuelType: string implements HasLabel
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case Electric = 'electric';

    public function getLabel(): string
    {
        return match ($this) {
            self::Petrol => 'Petrol',
            self::Diesel => 'Diesel',
            self::Hybrid => 'Hybrid',
            self::Electric => 'Electric',
        };
    }
}
