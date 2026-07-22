<?php

namespace App\Filament\Operator\Resources\ServiceRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('panel.service_section'))
                    ->columns(2)
                    ->components([
                        Select::make('vehicle_id')
                            ->label(__('panel.vehicle'))
                            ->relationship('vehicle', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('service_type')
                            ->label(__('panel.service_type'))
                            ->options(function (): array {
                                /** @var array<int, string> $types */
                                $types = config('maintenance.service_types');

                                return collect($types)
                                    ->mapWithKeys(fn (string $type): array => [$type => __('panel.service_type_'.$type)])
                                    ->all();
                            })
                            ->native(false)
                            ->required(),
                        DatePicker::make('performed_on')
                            ->label(__('panel.service_performed_on'))
                            ->default(now())
                            ->required(),
                        TextInput::make('odometer')
                            ->label(__('panel.service_odometer'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('km'),
                        TextInput::make('cost')
                            ->label(__('panel.service_cost'))
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€'),
                        DatePicker::make('next_due_on')
                            ->label(__('panel.service_next_due_on'))
                            ->after('performed_on'),
                        TextInput::make('next_due_odometer')
                            ->label(__('panel.service_next_due_odometer'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix('km'),
                        Textarea::make('notes')
                            ->label(__('panel.service_notes'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
