<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Active => 'success',
            self::Completed => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /** @return array<self> */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed, self::Active];
    }

    /**
     * Statuses that represent an actually-redeemed promo use — excludes Pending
     * (not yet a real commitment) and Cancelled (never became one).
     *
     * @return array<self>
     */
    public static function countsTowardPromoCap(): array
    {
        return [self::Confirmed, self::Active, self::Completed];
    }

    /**
     * Literal hex for the availability calendar (AvailabilityCalendar), which needs a
     * real CSS color for FullCalendar's backgroundColor, not a Filament palette token.
     * Single source of truth shared by the event fill and the calendar legend.
     */
    public function calendarColor(): string
    {
        return match ($this) {
            self::Pending => '#f59e0b',
            self::Confirmed => '#3b82f6',
            self::Active => '#22c55e',
            default => '#6b7280',
        };
    }
}
