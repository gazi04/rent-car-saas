<?php

namespace App\Filament\Operator\Resources\ServiceRecords\Pages;

use App\Filament\Operator\Resources\ServiceRecords\ServiceRecordResource;
use App\Filament\Support\HelpAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceRecords extends ListRecords
{
    protected static string $resource = ServiceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('service_records'),
            CreateAction::make(),
        ];
    }
}
