<?php

namespace App\Filament\Resources\Plans\Schemas;

use App\Enums\PlanFeature;
use App\Enums\PlanFeatureType;
use Filament\Forms\Components\CheckboxList;
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
                            ->label('Internal note')
                            ->helperText('Not shown publicly.')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active (selectable for tenants and payments)')
                            ->default(true)
                            ->columnSpanFull(),
                        Toggle::make('is_public')
                            ->label('Show on public pricing page')
                            ->helperText('Off = a private/custom plan: still assignable to a tenant and billable, but hidden from the marketing homepage.')
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

                // Copy for the public pricing card. The tagline is stored per
                // language; the bullets are a hand-picked subset of the feature
                // registry so their wording stays localized and typo-free.
                Section::make('Public pricing card')
                    ->description('Only used when "Show on public pricing page" is on.')
                    ->columns(2)
                    ->components([
                        TextInput::make('marketing_description.en')
                            ->label('Tagline (English)')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('marketing_description.sq')
                            ->label('Tagline (Albanian)')
                            ->required()
                            ->maxLength(120),
                        CheckboxList::make('marketing_highlights')
                            ->label('Highlighted features')
                            ->options(collect(PlanFeature::marketingOrder())
                                ->mapWithKeys(fn (PlanFeature $feature): array => [$feature->value => $feature->getLabel()])
                                ->all())
                            ->columns(2)
                            ->rule('array')
                            ->rule('max:6')
                            ->helperText('Pick 3–6. They render as the card bullets, in the order shown here. The three baseline items (dashboard, agreements, bilingual site) always show and are not listed here.')
                            ->columnSpanFull(),
                    ]),
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
