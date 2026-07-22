<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\RelationManagers;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Actionable per-tenant view of a tenant's operator/staff accounts, on the
 * Tenant detail page. Create/edit force-fill the not-mass-assignable role /
 * tenant_id / email_verified_at columns via the shared UsersTable helpers.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $title = 'Users';

    public function form(Schema $schema): Schema
    {
        // Tenant is fixed by the owning record — hide the tenant selector.
        return UserForm::configure($schema, includeTenant: false);
    }

    public function table(Table $table): Table
    {
        return UsersTable::configure($table, includeTenant: false)
            ->headerActions([
                CreateAction::make()
                    ->before(function (array $data, CreateAction $action): void {
                        if (($data['role'] ?? null) === 'operator'
                            && UsersTable::tenantHasOperator($this->getTenantId())) {
                            Notification::make()
                                ->title('This tenant already has an owner')
                                ->body('Each tenant has a single operator (owner). Add a staff account instead.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->using(fn (array $data): Model => UsersTable::createTenantUser($data, $this->getTenantId())),
            ])
            ->recordActions([
                UsersTable::verifyEmailAction(),
                UsersTable::resetPasswordAction(),
                EditAction::make()
                    ->before(function (array $data, User $record, EditAction $action): void {
                        if (($data['role'] ?? null) === 'operator'
                            && UsersTable::tenantHasOperator($this->getTenantId(), (int) $record->getKey())) {
                            Notification::make()
                                ->title('This tenant already has an owner')
                                ->body('Only one account can hold the owner role.')
                                ->danger()
                                ->send();

                            $action->halt();
                        }
                    })
                    ->using(fn (User $record, array $data): Model => UsersTable::updateTenantUser($record, $data)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function getTenantId(): int
    {
        /** @var Tenant $tenant */
        $tenant = $this->getOwnerRecord();

        return (int) $tenant->getKey();
    }
}
