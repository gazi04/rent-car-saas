<?php

namespace App\Filament\Operator\Resources\Bookings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        // tenant_id and totals never appear here — BelongsToTenant fills tenant_id,
        // and BookingService::createManual() computes pricing under the vehicle lock.
        return $schema
            ->components([
                Section::make('Vehicle & Dates')
                    ->columns(2)
                    ->components([
                        Select::make('vehicle_id')
                            ->relationship('vehicle', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DateTimePicker::make('start_date')
                            ->required()
                            ->seconds(false),
                        DateTimePicker::make('end_date')
                            ->required()
                            ->seconds(false)
                            ->after('start_date'),
                        TextInput::make('pickup_location')
                            ->maxLength(255),
                    ]),

                Section::make('Customer')
                    ->columns(2)
                    ->components([
                        TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('customer_phone')
                            ->required()
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
