<?php

namespace App\Filament\Operator\Resources\PromoCodes;

use App\Enums\PlanFeature;
use App\Filament\Operator\Resources\PromoCodes\Pages\CreatePromoCode;
use App\Filament\Operator\Resources\PromoCodes\Pages\EditPromoCode;
use App\Filament\Operator\Resources\PromoCodes\Pages\ListPromoCodes;
use App\Filament\Operator\Resources\PromoCodes\Schemas\PromoCodeForm;
use App\Filament\Operator\Resources\PromoCodes\Tables\PromoCodesTable;
use App\Models\PromoCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Operator-managed discount codes (backlog #8). Owner-only + plan-gated. Tenant
 * isolation is automatic via BelongsToTenant on PromoCode.
 */
class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_promo_codes');
    }

    /** Owner-only + plan-gated: hidden and 404 for staff and when the plan disables promo codes. */
    public static function canAccess(): bool
    {
        return (auth()->user()?->isOwner() ?? false)
            && (tenant()?->allowsFeature(PlanFeature::PromoCodes) ?? true);
    }

    public static function form(Schema $schema): Schema
    {
        return PromoCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoCodesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit' => EditPromoCode::route('/{record}/edit'),
        ];
    }
}
