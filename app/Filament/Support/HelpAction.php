<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Lang;

final class HelpAction
{
    public static function make(string $key): Action
    {
        return Action::make('help')
            ->label('')
            ->tooltip(__('help.tooltip'))
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->modalHeading(Lang::has(sprintf('help.%s.title', $key)) ? __(sprintf('help.%s.title', $key)) : __('help.fallback_title'))
            ->modalContent(view('filament.help-modal-content', ['key' => $key]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->slideOver(false);
    }
}
