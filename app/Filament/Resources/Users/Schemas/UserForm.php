<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Tenant;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    /**
     * Shared operator/staff form. `role` and `tenant_id` are ordinary fields
     * here (so their values reach $data), but the pages/actions force-fill them
     * because they aren't mass-assignable on User. The 'password' cast hashes
     * the value, so no manual Hash::make.
     *
     * @param  bool  $includeTenant  Show the tenant selector (false inside the
     *                               Tenant relation manager, where it's fixed).
     */
    public static function configure(Schema $schema, bool $includeTenant = true): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),
                        Select::make('role')
                            ->options([
                                'operator' => 'Operator (owner)',
                                'staff' => 'Staff',
                            ])
                            ->required()
                            ->native(false),
                        ...($includeTenant ? [
                            Select::make('tenant_id')
                                ->label('Tenant')
                                ->options(fn (): array => Tenant::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required()
                                // Moving a user between tenants is out of scope.
                                ->disabledOn('edit'),
                        ] : []),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            // Required on create; on edit a blank field keeps the
                            // current password (dehydrated only when filled).
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? 'Leave blank to keep the current password.'
                                : null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
