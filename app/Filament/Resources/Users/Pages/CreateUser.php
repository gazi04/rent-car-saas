<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * One-owner-per-tenant guardrail: block a second operator (owner) for a
     * tenant that already has one. Mirrors the seat-limit backstop in
     * CreateStaff::beforeCreate().
     */
    protected function beforeCreate(): void
    {
        if (($this->data['role'] ?? null) === 'operator'
            && UsersTable::tenantHasOperator((int) $this->data['tenant_id'])) {
            Notification::make()
                ->title('This tenant already has an owner')
                ->body('Each tenant has a single operator (owner). Add a staff account instead, or remove the existing owner first.')
                ->danger()
                ->send();

            $this->halt();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return UsersTable::createTenantUser($data, (int) $data['tenant_id']);
    }
}
