<?php

namespace App\Filament\Operator\Pages;

use App\Enums\PlanFeature;
use App\Filament\Support\HelpAction;
use App\Models\Tenant;
use BackedEnum;
use Filament\Actions\Action;
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
 * Operator-authored contract & email wording (feature #7). Owner-only + plan-gated.
 * Every field name is a tenant_settings key ({tmpl_*}_{locale}) so mount()/save()
 * treat them like any other localized setting; blank clears the override back to
 * the built-in default.
 *
 * @property-read Schema $form
 */
class TemplateSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.operator.pages.template-settings';

    /** Owner-only + plan-gated: hidden and 404 for staff, and when the plan disables templates. */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (Tenant::current()?->allowsFeature(PlanFeature::Templates) ?? (bool) PlanFeature::Templates->default());
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_templates');
    }

    public function getTitle(): string
    {
        return __('panel.nav_templates');
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Tenant::currentOrFail()->settings());
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('templates'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('templates')
                    ->tabs([
                        Tab::make(__('panel.tmpl_tab_agreement'))
                            ->components([
                                Tabs::make('agreement_locales')->tabs([
                                    Tab::make(__('panel.content_lang_sq'))->components($this->agreementFields('sq')),
                                    Tab::make(__('panel.content_lang_en'))->components($this->agreementFields('en')),
                                ]),
                            ]),

                        Tab::make(__('panel.tmpl_tab_emails'))
                            ->components([
                                Tabs::make('email_locales')->tabs([
                                    Tab::make(__('panel.content_lang_sq'))->components($this->emailFields('sq')),
                                    Tab::make(__('panel.content_lang_en'))->components($this->emailFields('en')),
                                ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    protected function agreementFields(string $locale): array
    {
        return [
            Section::make(__('panel.tmpl_terms'))
                ->description($this->variablesHint())
                ->components([
                    Textarea::make('tmpl_agreement_terms_'.$locale)
                        ->label(__('panel.tmpl_terms'))
                        ->rows(10)
                        ->maxLength(5000),
                ]),
        ];
    }

    /**
     * One section per customer email (subject + body + closing) for a locale.
     *
     * @return array<int, Section>
     */
    protected function emailFields(string $locale): array
    {
        /** @var array<int, string> $events */
        $events = config('templates.emails', []);

        return collect($events)->map(
            fn (string $event): Section => Section::make(__('panel.tmpl_email_'.$event))
                ->description($this->variablesHint())
                ->components([
                    TextInput::make(sprintf('tmpl_email_%s_subject_%s', $event, $locale))
                        ->label(__('panel.tmpl_subject'))
                        ->maxLength(255),
                    Textarea::make(sprintf('tmpl_email_%s_intro_%s', $event, $locale))
                        ->label(__('panel.tmpl_intro'))
                        ->rows(3)
                        ->maxLength(2000),
                    Textarea::make(sprintf('tmpl_email_%s_outro_%s', $event, $locale))
                        ->label(__('panel.tmpl_outro'))
                        ->rows(2)
                        ->maxLength(1000),
                ]),
        )->all();
    }

    protected function variablesHint(): string
    {
        /** @var array<int, string> $variables */
        $variables = config('templates.variables', []);

        $tokens = collect($variables)->map(fn (string $v): string => '{'.$v.'}')->implode(', ');

        return __('panel.tmpl_variables_hint', ['variables' => $tokens]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var array<int, string> $allowedKeys */
        $allowedKeys = config('templates.keys', []);

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                Tenant::currentOrFail()->setSetting($key, $data[$key]);
            }
        }

        Notification::make()
            ->title(__('panel.tmpl_saved'))
            ->success()
            ->send();
    }
}
