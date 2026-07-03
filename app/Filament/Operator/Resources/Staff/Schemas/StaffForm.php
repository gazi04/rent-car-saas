<?php

namespace App\Filament\Operator\Resources\Staff\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        // role and tenant_id are never fields — CreateStaff force-fills them.
        // The User 'password' cast hashes the value, so no manual Hash::make.
        return $schema
            ->components([
                Section::make(__('panel.section_staff'))
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label(__('panel.staff_name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('panel.staff_email'))
                            ->required()
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label(__('panel.staff_password'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            // Required on create; on edit a blank field keeps the
                            // current password (dehydrated only when filled).
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                ? (string) __('panel.staff_password_hint')
                                : null),
                    ]),
            ]);
    }
}
