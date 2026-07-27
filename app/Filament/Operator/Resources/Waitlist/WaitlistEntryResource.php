<?php

namespace App\Filament\Operator\Resources\Waitlist;

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\Waitlist\Pages\ListWaitlistEntries;
use App\Filament\Operator\Resources\Waitlist\Tables\WaitlistEntriesTable;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Demand the operator would otherwise never see: who wanted which vehicle, when
 * (backlog #2), and who is waiting for one to come back (#3). Owner-only +
 * plan-gated. Tenant isolation is automatic via BelongsToTenant on WaitlistEntry.
 *
 * Read-only by design — entries come from the public site, so there is no create
 * or edit page, only list + delete.
 */
class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_waitlist');
    }

    public static function getModelLabel(): string
    {
        return __('panel.model_label_waitlist_entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav_waitlist');
    }

    /**
     * Owner-only + plan-gated: hidden and 404 for staff, and when the plan enables
     * neither feature.
     *
     * Either toggle opens it, because both entry types live in this one list —
     * requiring Waitlist alone would leave a StockAlert-only tenant collecting
     * entries they could never read.
     */
    public static function canAccess(): bool
    {
        if (! (auth()->user()?->isOwner() ?? false)) {
            return false;
        }

        if (self::allowsFeature(PlanFeature::Waitlist)) {
            return true;
        }

        return self::allowsFeature(PlanFeature::StockAlert);
    }

    private static function allowsFeature(PlanFeature $feature): bool
    {
        return Tenant::current()?->allowsFeature($feature) ?? (bool) $feature->default();
    }

    public static function table(Table $table): Table
    {
        return WaitlistEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitlistEntries::route('/'),
        ];
    }
}
