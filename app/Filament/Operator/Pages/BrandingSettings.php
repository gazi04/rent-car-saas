<?php

namespace App\Filament\Operator\Pages;

use App\Enums\PlanFeature;
use App\Filament\Support\HelpAction;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class BrandingSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.operator.pages.branding-settings';

    /**
     * Owner-only + plan-gated: hidden and 404 for staff accounts, and when the
     * plan disables branding.
     */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (tenant()?->allowsFeature(PlanFeature::Branding) ?? (bool) PlanFeature::Branding->default());
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = tenant()->settings();
        $logoMedia = tenant()->getFirstMedia('logo');

        // Content saved before the bilingual split lives under the un-suffixed
        // key; surface it in the Albanian fields so it isn't invisible here
        // (the public page falls back to it either way).
        /** @var array<int, string> $localizedKeys */
        $localizedKeys = config('branding.localized_keys', []);

        foreach ($localizedKeys as $key) {
            if (! isset($settings["{$key}_sq"]) && isset($settings[$key])) {
                $settings["{$key}_sq"] = $settings[$key];
            }
        }

        $this->form->fill(array_merge($settings, [
            'logo' => $logoMedia ? [$logoMedia->uuid] : [],
        ]));
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('branding'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        /** @var array<string, array{label: string, url: string}> $fonts */
        $fonts = config('branding.fonts', []);

        /** @var array<string, string> $fontOptions */
        $fontOptions = array_map(fn (array $f): string => $f['label'], $fonts);

        return $schema
            ->statePath('data')
            ->model(tenant())
            ->components([
                Tabs::make('branding')
                    ->tabs([
                        Tab::make(__('branding.tab_brand'))
                            ->components([
                                Section::make(__('branding.section_logo'))
                                    ->components([
                                        SpatieMediaLibraryFileUpload::make('logo')
                                            ->label(__('branding.logo'))
                                            ->collection('logo')
                                            ->disk('public')
                                            ->image()
                                            ->maxSize(2048)
                                            ->helperText(__('branding.logo_hint')),
                                    ]),

                                Section::make(__('branding.section_colors'))
                                    ->columns(2)
                                    ->components([
                                        ColorPicker::make('color_primary')
                                            ->label(__('branding.color_primary'))
                                            ->rule('regex:'.config('branding.color_format')),
                                        ColorPicker::make('color_secondary')
                                            ->label(__('branding.color_secondary'))
                                            ->rule('regex:'.config('branding.color_format')),
                                    ]),

                                Section::make(__('branding.section_font'))
                                    ->components([
                                        Select::make('font_family')
                                            ->label(__('branding.font_family'))
                                            ->options($fontOptions)
                                            ->native(false),
                                    ]),
                            ]),

                        Tab::make(__('branding.tab_content'))
                            ->components([
                                // One field set per public-site language: what the
                                // operator writes under "Shqip" is what visitors see
                                // with the site in Albanian, and likewise for English.
                                Tabs::make('content_locales')
                                    ->tabs([
                                        Tab::make(__('branding.content_lang_sq'))
                                            ->components($this->contentFields('sq')),
                                        Tab::make(__('branding.content_lang_en'))
                                            ->components($this->contentFields('en')),
                                    ]),
                            ]),

                        Tab::make(__('branding.tab_layout'))
                            ->components([
                                Section::make(__('branding.section_layouts'))
                                    ->description(__('branding.layouts_hint'))
                                    ->components([
                                        Select::make('layout_home')
                                            ->label(__('branding.layout_home'))
                                            ->options($this->layoutOptions('home'))
                                            ->native(false),
                                        Select::make('layout_vehicles')
                                            ->label(__('branding.layout_vehicles'))
                                            ->options($this->layoutOptions('vehicles'))
                                            ->native(false),
                                        Select::make('layout_vehicle_show')
                                            ->label(__('branding.layout_vehicle_show'))
                                            ->options($this->layoutOptions('vehicle_show'))
                                            ->native(false),
                                    ]),
                            ]),

                        Tab::make(__('branding.tab_contact_footer'))
                            ->components([
                                Section::make(__('branding.section_contact'))
                                    ->columns(2)
                                    ->components([
                                        TextInput::make('contact_phone')
                                            ->label(__('branding.contact_phone'))
                                            ->tel()
                                            ->maxLength(30),
                                        TextInput::make('contact_email')
                                            ->label(__('branding.contact_email'))
                                            ->email()
                                            ->maxLength(100),
                                        Textarea::make('contact_address')
                                            ->label(__('branding.contact_address'))
                                            ->rows(2)
                                            ->maxLength(300)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('branding.section_footer'))
                                    ->components([
                                        Textarea::make('footer_text_sq')
                                            ->label(__('branding.footer_text').' ('.__('branding.content_lang_sq').')')
                                            ->rows(2)
                                            ->maxLength(500),
                                        Textarea::make('footer_text_en')
                                            ->label(__('branding.footer_text').' ('.__('branding.content_lang_en').')')
                                            ->rows(2)
                                            ->maxLength(500),
                                        TextInput::make('social_facebook')
                                            ->label(__('branding.social_facebook'))
                                            ->url()
                                            ->maxLength(255),
                                        TextInput::make('social_instagram')
                                            ->label(__('branding.social_instagram'))
                                            ->url()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * The home-page content field set for one public-site language. Field names
     * are the localized setting keys ({key}_{locale}) so mount()/save() handle
     * them like any other setting.
     *
     * @return array<int, Section>
     */
    protected function contentFields(string $locale): array
    {
        return [
            Section::make(__('branding.section_hero'))
                ->description(__('branding.content_hint'))
                ->components([
                    TextInput::make("home_hero_heading_{$locale}")
                        ->label(__('branding.home_hero_heading'))
                        ->maxLength(120),
                    Textarea::make("home_hero_subheading_{$locale}")
                        ->label(__('branding.home_hero_subheading'))
                        ->rows(2)
                        ->maxLength(300),
                    TextInput::make("home_hero_cta_label_{$locale}")
                        ->label(__('branding.home_hero_cta_label'))
                        ->maxLength(40),
                ]),

            Section::make(__('branding.section_about'))
                ->components([
                    TextInput::make("home_about_title_{$locale}")
                        ->label(__('branding.home_about_title'))
                        ->maxLength(120),
                    Textarea::make("home_about_text_{$locale}")
                        ->label(__('branding.home_about_text'))
                        ->rows(4)
                        ->maxLength(2000),
                ]),

            ...collect([1, 2, 3])->map(
                fn (int $i): Section => Section::make(__("branding.section_service_{$i}"))
                    ->columns(2)
                    ->components([
                        TextInput::make("home_service_{$i}_title_{$locale}")
                            ->label(__('branding.service_title'))
                            ->maxLength(80),
                        Textarea::make("home_service_{$i}_text_{$locale}")
                            ->label(__('branding.service_text'))
                            ->rows(2)
                            ->maxLength(300),
                    ]),
            )->all(),
        ];
    }

    /**
     * Curated layout options for one public page, labeled for the current locale.
     *
     * @return array<string, string>
     */
    protected function layoutOptions(string $page): array
    {
        /** @var array<int, string> $slugs */
        $slugs = config("branding.layouts.{$page}", []);

        return collect($slugs)
            ->mapWithKeys(fn (string $slug): array => [
                $slug => __('branding.layout_'.str_replace('-', '_', $slug)),
            ])
            ->all();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var array<int, string> $allowedKeys */
        $allowedKeys = config('branding.keys', []);

        $layoutPages = [
            'layout_home' => 'home',
            'layout_vehicles' => 'vehicles',
            'layout_vehicle_show' => 'vehicle_show',
        ];

        foreach ($allowedKeys as $key) {
            if ($key === 'font_family' && isset($data[$key])) {
                /** @var array<string, mixed> $fonts */
                $fonts = config('branding.fonts', []);
                if (! array_key_exists($data[$key], $fonts)) {
                    continue;
                }
            }

            if (isset($layoutPages[$key], $data[$key])) {
                /** @var array<int, string> $allowedLayouts */
                $allowedLayouts = config('branding.layouts.'.$layoutPages[$key], []);
                if (! in_array($data[$key], $allowedLayouts, true)) {
                    continue;
                }
            }

            if (array_key_exists($key, $data)) {
                tenant()->setSetting($key, $data[$key]);
            }
        }

        $this->form->model(tenant())->saveRelationships();

        Notification::make()
            ->title(__('branding.saved'))
            ->success()
            ->send();
    }

    public static function getNavigationLabel(): string
    {
        return __('branding.navigation_label');
    }
}
