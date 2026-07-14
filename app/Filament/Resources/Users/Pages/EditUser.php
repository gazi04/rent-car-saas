<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Guard against promoting a staff account to owner when the tenant already
     * has one (ignoring this record).
     */
    protected function beforeSave(): void
    {
        /** @var User $record */
        $record = $this->getRecord();

        if (($this->data['role'] ?? null) === 'operator'
            && UsersTable::tenantHasOperator((int) $record->tenant_id, (int) $record->getKey())) {
            Notification::make()
                ->title('This tenant already has an owner')
                ->body('Each tenant has a single operator (owner). Only one account can hold the owner role.')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        return UsersTable::updateTenantUser($record, $data);
    }
}
