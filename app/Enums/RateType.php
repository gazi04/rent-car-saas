<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RateType: string implements HasLabel
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }
}
