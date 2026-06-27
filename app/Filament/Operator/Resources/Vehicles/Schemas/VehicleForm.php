<?php

namespace App\Filament\Operator\Resources\Vehicles\Schemas;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        // tenant_id is never edited here — BelongsToTenant auto-fills it from the
        // current operator's tenant context.
        return $schema
            ->components([
                Section::make('Basics')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('category')
                            ->options(VehicleCategory::class)
                            ->required(),
                        TextInput::make('year')
                            ->required()
                            ->numeric()
                            ->minValue(1950)
                            ->maxValue((int) date('Y') + 1),
                        Select::make('fuel_type')
                            ->options(FuelType::class)
                            ->required(),
                        Select::make('transmission')
                            ->options(Transmission::class)
                            ->required(),
                        TextInput::make('seats')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50),
                    ]),

                Section::make('Pricing')
                    ->columns(2)
                    ->components([
                        TextInput::make('daily_rate')
                            ->required()
                            ->numeric()
                            ->prefix('€'),
                        TextInput::make('hourly_rate')
                            ->numeric()
                            ->prefix('€'),
                        TextInput::make('weekly_rate')
                            ->numeric()
                            ->prefix('€'),
                        TextInput::make('monthly_rate')
                            ->numeric()
                            ->prefix('€'),
                        Select::make('discount_type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed' => 'Fixed amount',
                            ])
                            ->native(false),
                        TextInput::make('discount_value')
                            ->numeric(),
                        TextInput::make('mileage_limit')
                            ->numeric()
                            ->suffix('km/day'),
                        TextInput::make('deposit')
                            ->numeric()
                            ->prefix('€'),
                    ]),

                Section::make('Photos')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->collection('vehicle_photos')
                            ->multiple()
                            ->maxFiles(8)
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->helperText('The first photo is used as the cover.'),
                    ]),

                Section::make('Custom fields')
                    ->components([
                        Repeater::make('custom_fields')
                            ->label('Custom fields')
                            ->schema([
                                TextInput::make('label')
                                    ->required(),
                                TextInput::make('value')
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add field')
                            ->default([]),
                    ]),

                Section::make('Visibility')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_public')
                            ->label('Show on public booking page')
                            ->default(true),
                        Select::make('status')
                            ->options(VehicleStatus::class)
                            ->default(VehicleStatus::Available->value)
                            ->required(),
                        Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
