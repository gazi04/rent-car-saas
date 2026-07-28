<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanFeature;
use App\Enums\PlanFeatureType;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(100)
                            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            // Immutable after creation: tenants reference plans by slug.
                            ->disabledOn('edit')
                            ->dehydrated()
                            ->unique(ignoreRecord: true),
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('€')
                            ->suffix('/ month'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active (selectable for tenants and payments)')
                            ->default(true)
                            ->columnSpanFull(),
                        Toggle::make('is_trial')
                            ->label('Trial plan')
                            ->helperText('Marks this as the trial tier — flagging it here automatically unflags any other plan. Self-registration and trial-status checks key off whichever plan has this on.')
                            ->default(false)
                            ->columnSpanFull(),
                    ]),

                // One control per PlanFeature case — a new enum case shows up
                // here automatically, no form changes needed.
                Section::make('Features')
                    ->description('What this plan unlocks. Leave a limit empty for unlimited.')
                    ->columns(2)
                    ->components(self::featureFields()),
            ]);
    }

    /** @return array<int, Component> */
    protected static function featureFields(): array
    {
        return collect(PlanFeature::cases())
            ->map(fn (PlanFeature $feature): Component => match ($feature->type()) {
                PlanFeatureType::Toggle => Toggle::make('features.'.$feature->value)
                    ->label($feature->getLabel())
                    ->default((bool) $feature->default())
                    ->inline(false),
                PlanFeatureType::Limit => TextInput::make('features.'.$feature->value)
                    ->label($feature->getLabel())
                    ->integer()
                    ->minValue(0)
                    ->placeholder('Unlimited'),
            })
            ->all();
    }
}
