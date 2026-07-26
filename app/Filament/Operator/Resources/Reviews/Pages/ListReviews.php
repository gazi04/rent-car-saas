<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Reviews\Pages;

use App\Filament\Operator\Resources\Reviews\ReviewResource;
use App\Filament\Support\HelpAction;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    // No create action — reviews are submitted by customers via a signed link.
    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('reviews'),
        ];
    }
}
