<?php

namespace App\Filament\Operator\Resources\PromoCodes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        // tenant_id is never edited here — BelongsToTenant fills it. Codes are
        // stored uppercased so lookups are case-insensitive.
        return $schema
            ->components([
                Section::make(__('panel.promo_section'))
                    ->columns(2)
                    ->components([
                        TextInput::make('code')
                            ->label(__('panel.promo_code'))
                            ->required()
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn (string $state): string => strtoupper(trim($state)))
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->label(__('panel.promo_is_active'))
                            ->default(true),
                        Select::make('type')
                            ->label(__('panel.promo_type'))
                            ->options([
                                'percentage' => __('panel.promo_percentage'),
                                'fixed' => __('panel.promo_fixed'),
                            ])
                            ->default('percentage')
                            ->native(false)
                            ->required(),
                        TextInput::make('value')
                            ->label(__('panel.promo_value'))
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        DatePicker::make('starts_at')
                            ->label(__('panel.promo_starts_at')),
                        DatePicker::make('expires_at')
                            ->label(__('panel.promo_expires_at'))
                            ->after('starts_at'),
                        TextInput::make('max_uses')
                            ->label(__('panel.promo_max_uses'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('panel.promo_unlimited_hint')),
                        TextInput::make('per_customer_limit')
                            ->label(__('panel.promo_per_customer_limit'))
                            ->numeric()
                            ->minValue(1)
                            ->helperText(__('panel.promo_unlimited_hint')),
                    ]),
            ]);
    }
}
