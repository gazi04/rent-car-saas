<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Transmission: string implements HasLabel
{
    case Manual = 'manual';
    case Automatic = 'automatic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Automatic => 'Automatic',
        };
    }
}
