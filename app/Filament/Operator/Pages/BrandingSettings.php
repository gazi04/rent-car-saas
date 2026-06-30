<?php

namespace App\Filament\Operator\Pages;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = tenant()->settings();
        $logoMedia = tenant()->getFirstMedia('logo');

        $this->form->fill(array_merge($settings, [
            'logo' => $logoMedia ? [$logoMedia->uuid] : [],
        ]));
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
                            ->rule('regex:/^#[0-9a-fA-F]{6}$/'),
                        ColorPicker::make('color_secondary')
                            ->label(__('branding.color_secondary'))
                            ->rule('regex:/^#[0-9a-fA-F]{6}$/'),
                    ]),

                Section::make(__('branding.section_font'))
                    ->components([
                        Select::make('font_family')
                            ->label(__('branding.font_family'))
                            ->options($fontOptions)
                            ->native(false),
                    ]),

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
                        Textarea::make('footer_text')
                            ->label(__('branding.footer_text'))
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

            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var array<int, string> $allowedKeys */
        $allowedKeys = config('branding.keys', []);

        foreach ($allowedKeys as $key) {
            if ($key === 'font_family' && isset($data[$key])) {
                /** @var array<string, mixed> $fonts */
                $fonts = config('branding.fonts', []);
                if (! array_key_exists($data[$key], $fonts)) {
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
