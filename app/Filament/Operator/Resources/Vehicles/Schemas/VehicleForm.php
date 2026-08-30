<?php

namespace App\Filament\Operator\Resources\Vehicles\Schemas;

use App\Enums\FuelType;
use App\Enums\PlanFeature;
use App\Enums\Transmission;
use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use App\Exceptions\AiRequestFailedException;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Services\Ai\PricingSuggestionService;
use App\Services\Ai\VehicleListingWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {

        // tenant_id is never edited here — BelongsToTenant auto-fills it from the
        // current operator's tenant context.
        return $schema
            ->components([
                Section::make(__('panel.section_basics'))
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label(__('panel.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('plate')
                            ->label(__('panel.plate'))
                            ->maxLength(20)
                            // ->unique() runs a raw DB-table check that bypasses the
                            // BelongsToTenant global scope; ->scopedUnique() queries
                            // through Vehicle::query() so it's correctly tenant-scoped.
                            ->scopedUnique(ignoreRecord: true),
                        Select::make('category')
                            ->label(__('panel.category'))
                            ->options(VehicleCategory::class)
                            ->required(),
                        TextInput::make('year')
                            ->label(__('panel.year'))
                            ->required()
                            ->numeric()
                            ->minValue(1950)
                            ->maxValue((int) date('Y') + 1),
                        Select::make('fuel_type')
                            ->label(__('panel.fuel_type'))
                            ->options(FuelType::class)
                            ->required(),
                        Select::make('transmission')
                            ->label(__('panel.transmission'))
                            ->options(Transmission::class)
                            ->required(),
                        TextInput::make('seats')
                            ->label(__('panel.seats'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50),
                    ]),

                Section::make(__('panel.section_pricing'))
                    ->columns(2)
                    ->components([
                        TextInput::make('daily_rate')
                            ->label(__('panel.daily_rate'))
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->live(onBlur: true)
                            // Pricing suggestion needs booking history, so it is
                            // only offered once the vehicle exists (edit form).
                            ->hintAction(
                                Action::make('suggestPrice')
                                    ->label(__('panel.ai_suggest_price'))
                                    ->icon('heroicon-m-sparkles')
                                    ->visible(fn (?Vehicle $record): bool => $record instanceof Vehicle
                                        && (Tenant::current()?->allowsFeature(PlanFeature::AiPricingSuggestions) ?? (bool) PlanFeature::AiPricingSuggestions->default()))
                                    ->requiresConfirmation()
                                    ->modalHeading(__('panel.ai_suggest_price'))
                                    ->modalDescription(__('panel.ai_suggest_price_confirm'))
                                    ->modalSubmitActionLabel(__('panel.ai_apply_rate'))
                                    // The AI runs only on confirm — never on modal mount — so
                                    // the confirmation modal opens instantly instead of hanging
                                    // on the multi-second AI round-trip.
                                    ->action(function (Vehicle $record, Set $set): void {
                                        try {
                                            $suggestion = resolve(PricingSuggestionService::class)->suggest($record, app()->getLocale());
                                        } catch (AiRequestFailedException) {
                                            Notification::make()->title(__('panel.ai_error'))->danger()->send();

                                            return;
                                        }

                                        $set('daily_rate', $suggestion['suggested_daily_rate']);

                                        Notification::make()
                                            ->title(__('panel.ai_suggested_rate', [
                                                'rate' => number_format($suggestion['suggested_daily_rate'], 2),
                                            ]))
                                            ->body($suggestion['reasoning'])
                                            ->success()
                                            ->send();
                                    })
                            ),
                        TextInput::make('hourly_rate')
                            ->label(__('panel.hourly_rate'))
                            ->numeric()
                            ->prefix('€'),
                        // The warning is informational, not a validation rule: an
                        // inconsistent rate simply never gets charged
                        // (PricingService::selectRate() always picks the
                        // cheapest applicable tier), so this only helps the
                        // operator notice before it confuses a customer.
                        TextInput::make('weekly_rate')
                            ->label(__('panel.weekly_rate'))
                            ->numeric()
                            ->prefix('€')
                            ->live(onBlur: true)
                            ->helperText(function (Get $get): ?string {
                                $daily = self::toFloat($get('daily_rate'));
                                $weekly = self::toFloat($get('weekly_rate'));

                                return $weekly > 0 && $daily > 0 && $weekly > 7 * $daily
                                    ? (string) __('panel.weekly_rate_pricier_warning')
                                    : null;
                            }),
                        TextInput::make('monthly_rate')
                            ->label(__('panel.monthly_rate'))
                            ->numeric()
                            ->prefix('€')
                            ->live(onBlur: true)
                            ->helperText(function (Get $get): ?string {
                                $daily = self::toFloat($get('daily_rate'));
                                $monthly = self::toFloat($get('monthly_rate'));

                                return $monthly > 0 && $daily > 0 && $monthly > 30 * $daily
                                    ? (string) __('panel.monthly_rate_pricier_warning')
                                    : null;
                            }),
                        Select::make('discount_type')
                            ->label(__('panel.discount_type'))
                            ->options([
                                'percentage' => __('panel.discount_percentage'),
                                'fixed' => __('panel.discount_fixed'),
                            ])
                            ->native(false),
                        TextInput::make('discount_value')
                            ->label(__('panel.discount_value'))
                            ->numeric(),
                        TextInput::make('mileage_limit')
                            ->label(__('panel.mileage_limit'))
                            ->numeric()
                            ->suffix('km/day'),
                        TextInput::make('deposit')
                            ->label(__('panel.deposit'))
                            ->numeric()
                            ->prefix('€'),
                    ]),

                Section::make(__('panel.section_photos'))
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->label(__('panel.photos'))
                            ->collection('vehicle_photos')
                            ->disk(config()->string('media-library.disk_name'))
                            ->multiple()
                            // Plan cap; 8 stays the app-wide ceiling for unlimited plans.
                            ->maxFiles(fn (): int => min(Tenant::current()?->featureLimit(PlanFeature::PhotosPerVehicle) ?? 8, 8))
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->helperText(__('panel.photos_hint')),
                    ]),

                Section::make(__('panel.section_custom_fields'))
                    ->components([
                        Repeater::make('custom_fields')
                            ->label(__('panel.custom_fields'))
                            ->schema([
                                TextInput::make('label')
                                    ->label(__('panel.field_label'))
                                    ->required(),
                                TextInput::make('value')
                                    ->label(__('panel.field_value'))
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('panel.add_field'))
                            ->default([]),
                    ]),

                Section::make(__('panel.section_visibility'))
                    ->columns(2)
                    ->components([
                        Toggle::make('is_public')
                            ->label(__('panel.is_public'))
                            ->default(true),
                        Select::make('status')
                            ->label(__('panel.status'))
                            ->options(VehicleStatus::class)
                            ->default(VehicleStatus::Available->value)
                            ->required(),
                        Textarea::make('description.en')
                            ->label(__('panel.description_en'))
                            ->columnSpanFull()
                            // The AI writer fills both language boxes from one call, so the
                            // "generate" action lives on the English field and sets both.
                            ->hintAction(
                                Action::make('generateDescription')
                                    ->label(__('panel.ai_generate'))
                                    ->icon('heroicon-m-sparkles')
                                    ->visible(fn (): bool => Tenant::current()?->allowsFeature(PlanFeature::AiListingWriter) ?? (bool) PlanFeature::AiListingWriter->default())
                                    ->action(function (Get $get, Set $set, ?Vehicle $record): void {
                                        try {
                                            $description = resolve(VehicleListingWriter::class)->write(
                                                specs: [
                                                    'name' => $get('name'),
                                                    'category' => $get('category'),
                                                    'year' => $get('year'),
                                                    'fuel_type' => $get('fuel_type'),
                                                    'transmission' => $get('transmission'),
                                                    'seats' => $get('seats'),
                                                    'custom_fields' => $get('custom_fields'),
                                                ],
                                                vehicle: $record,
                                            );
                                        } catch (AiRequestFailedException) {
                                            Notification::make()->title(__('panel.ai_error'))->danger()->send();

                                            return;
                                        }

                                        $set('description.en', $description['en']);
                                        $set('description.sq', $description['sq']);

                                        Notification::make()->title(__('panel.ai_generated'))->success()->send();
                                    })
                            ),
                        Textarea::make('description.sq')
                            ->label(__('panel.description_sq'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Get::get() values from other form fields arrive as mixed. */
    private static function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
