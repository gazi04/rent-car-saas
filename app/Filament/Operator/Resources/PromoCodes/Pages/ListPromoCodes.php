<?php

namespace App\Filament\Operator\Resources\PromoCodes\Pages;

use App\Filament\Operator\Resources\PromoCodes\PromoCodeResource;
use App\Filament\Support\HelpAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoCodes extends ListRecords
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('promo_codes'),
            CreateAction::make(),
        ];
    }
}
