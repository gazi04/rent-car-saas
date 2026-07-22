<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\PromoCodes\Pages;

use App\Filament\Operator\Resources\PromoCodes\PromoCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;
}
