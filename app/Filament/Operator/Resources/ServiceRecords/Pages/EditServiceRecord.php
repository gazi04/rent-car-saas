<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\ServiceRecords\Pages;

use App\Filament\Operator\Resources\ServiceRecords\ServiceRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceRecord extends EditRecord
{
    protected static string $resource = ServiceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
