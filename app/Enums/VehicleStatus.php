<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VehicleStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Booked = 'booked';
    case UnderMaintenance = 'under_maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Booked => 'Booked',
            self::UnderMaintenance => 'Under maintenance',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Booked => 'warning',
            self::UnderMaintenance => 'danger',
        };
    }
}
