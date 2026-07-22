<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Waitlist\Pages;

use App\Filament\Operator\Resources\Waitlist\WaitlistEntryResource;
use App\Filament\Support\HelpAction;
use Filament\Resources\Pages\ListRecords;

class ListWaitlistEntries extends ListRecords
{
    protected static string $resource = WaitlistEntryResource::class;

    /** No CreateAction: entries only ever come from the public site. */
    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('waitlist'),
        ];
    }
}
