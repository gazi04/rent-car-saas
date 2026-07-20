<?php

namespace App\Filament\Operator\Resources\Staff;

use App\Filament\Operator\Resources\Staff\Pages\CreateStaff;
use App\Filament\Operator\Resources\Staff\Pages\EditStaff;
use App\Filament\Operator\Resources\Staff\Pages\ListStaff;
use App\Filament\Operator\Resources\Staff\Schemas\StaffForm;
use App\Filament\Operator\Resources\Staff\Tables\StaffTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Front-desk staff accounts for the current tenant (backlog #9). Owner-only —
 * staff can't manage other staff. Gated by a per-plan seat cap (see ListStaff).
 * The User model is central (no BelongsToTenant), so the query is scoped
 * manually to this tenant's staff rows.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_staff');
    }

    public static function getModelLabel(): string
    {
        return __('panel.model_label_staff');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.nav_staff');
    }

    /** Owner-only: hidden and 404 for staff accounts themselves. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        // User has no tenant global scope — restrict to this tenant's staff and
        // hide the owner (role = operator) from the list.
        return parent::getEloquentQuery()
            ->where('tenant_id', tenant('id'))
            ->where('role', 'staff');
    }

    public static function form(Schema $schema): Schema
    {
        return StaffForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StaffTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}
