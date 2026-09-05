<?php

namespace App\Filament\Operator\Resources\Customers\Schemas;

use App\Support\PhoneNumber;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        // tenant_id is never a field — BelongsToTenant fills it. The phone
        // uniqueness is scoped to the tenant by the same global scope, mirroring
        // the customers.[tenant_id, phone] DB constraint.
        return $schema
            ->components([
                Section::make(__('panel.section_customer'))
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label(__('panel.customer_name'))
                            ->required()
                            ->maxLength(255),
                        // Normalized in the VISIBLE state, not via
                        // dehydrateStateUsing(): dehydration runs after
                        // validation, so unique() would still compare the raw
                        // string and let "044 123 456" through alongside an
                        // existing "044123456". Doing it on blur means the
                        // operator sees the stored form and unique() checks it.
                        TextInput::make('phone')
                            ->label(__('panel.customer_phone'))
                            ->required()
                            ->tel()
                            ->maxLength(50)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('phone', PhoneNumber::normalize($state)))
                            ->unique(ignoreRecord: true),
                        TextInput::make('email')
                            ->label(__('panel.customer_email'))
                            ->email()
                            ->maxLength(255),
                        Toggle::make('is_blacklisted')
                            ->label(__('panel.is_blacklisted'))
                            ->helperText(__('panel.blacklist_hint')),
                        Textarea::make('notes')
                            ->label(__('panel.customer_notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
