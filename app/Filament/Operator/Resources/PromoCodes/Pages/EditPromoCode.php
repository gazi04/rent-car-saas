<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\PromoCodes\Pages;

use App\Filament\Operator\Resources\PromoCodes\PromoCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
