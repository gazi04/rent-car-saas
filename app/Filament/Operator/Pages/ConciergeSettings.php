<?php

namespace App\Filament\Operator\Pages;

use App\Enums\PlanFeature;
use App\Filament\Support\HelpAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Operator-authored FAQ knowledge base for the storefront AI concierge (#4).
 * Owner-only + plan-gated. Each field name is a tenant_settings key
 * (faq_content_{locale}) so mount()/save() treat it like any other localized
 * setting; blank means "no FAQ in this language" — the widget hides itself
 * when no content exists in either.
 *
 * A dedicated page (not a tab on BrandingSettings) keeps this concierge-gated
 * content off a Branding-gated page — the whole reason the FAQ lives in its
 * own config rather than config/branding.php.
 *
 * @property-read Schema $form
 */
class ConciergeSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.operator.pages.concierge-settings';

    /** Owner-only + plan-gated: hidden and 404 for staff, and when the plan disables the concierge. */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (tenant()?->allowsFeature(PlanFeature::AiConcierge) ?? (bool) PlanFeature::AiConcierge->default());
    }

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_concierge');
    }

    public function getTitle(): string
    {
        return __('panel.nav_concierge');
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(tenant()->settings());
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            HelpAction::make('concierge'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('panel.concierge_faq'))
                    ->description(__('panel.concierge_faq_hint'))
                    ->components([
                        Tabs::make('faq_locales')->tabs([
                            Tab::make(__('panel.content_lang_sq'))->components($this->faqFields('sq')),
                            Tab::make(__('panel.content_lang_en'))->components($this->faqFields('en')),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, Textarea>
     */
    protected function faqFields(string $locale): array
    {
        return [
            Textarea::make("faq_content_{$locale}")
                ->label(__('panel.concierge_faq'))
                ->placeholder(__('panel.concierge_faq_placeholder'))
                ->rows(14)
                ->maxLength(6000),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var array<int, string> $allowedKeys */
        $allowedKeys = config('concierge.keys', []);

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                tenant()->setSetting($key, $data[$key]);
            }
        }

        Notification::make()
            ->title(__('panel.concierge_saved'))
            ->success()
            ->send();
    }
}
