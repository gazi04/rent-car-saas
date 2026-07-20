<?php

namespace App\Filament\Operator\Resources\Reviews;

use App\Filament\Operator\Resources\Reviews\Pages\ListReviews;
use App\Filament\Operator\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Operator moderation of customer reviews. Owner-only,
 * but deliberately NOT plan-gated: collecting and moderating reviews is free on
 * every plan so the flywheel can spin — only the auto-request email and the
 * home-page showcase are gated (PlanFeature::Reviews). Reviews are created by
 * customers via a signed link, so there is no create action here; the operator
 * only approves, hides, or deletes them. Tenant isolation is automatic via
 * BelongsToTenant on Review.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'reviewer_name';

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_reviews');
    }

    public static function getModelLabel(): string
    {
        return __('panel.model_label_review');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav_reviews');
    }

    /** Owner-only (fleet/marketing data); free on every plan (not feature-gated). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }
}
