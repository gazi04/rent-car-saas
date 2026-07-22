<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\ServiceRecords\Pages;

use App\Filament\Operator\Resources\ServiceRecords\ServiceRecordResource;
use App\Models\BlockedDate;
use App\Models\ServiceRecord;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceRecord extends CreateRecord
{
    protected static string $resource = ServiceRecordResource::class;

    /**
     * Logging a fresh service closes the maintenance loop: the vehicle is now
     * serviced, so any active `maintenance` block the sweep created is lifted,
     * and the record it was tracking is unlinked (it has just been superseded
     * by this new entry).
     */
    protected function afterCreate(): void
    {
        /** @var ServiceRecord $record */
        $record = $this->record;
        $vehicleId = $record->vehicle_id;

        $blockedDateIds = BlockedDate::query()
            ->where('vehicle_id', $vehicleId)
            ->where('reason', 'maintenance')
            ->pluck('id');

        if ($blockedDateIds->isEmpty()) {
            return;
        }

        ServiceRecord::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('blocked_date_id', $blockedDateIds)
            ->update(['blocked_date_id' => null]);

        BlockedDate::query()->whereIn('id', $blockedDateIds)->delete();
    }
}
