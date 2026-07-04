<?php

namespace App\Filament\Operator\Resources\ServiceRecords;

use App\Filament\Operator\Resources\ServiceRecords\Pages\CreateServiceRecord;
use App\Filament\Operator\Resources\ServiceRecords\Pages\EditServiceRecord;
use App\Filament\Operator\Resources\ServiceRecords\Pages\ListServiceRecords;
use App\Filament\Operator\Resources\ServiceRecords\Schemas\ServiceRecordForm;
use App\Filament\Operator\Resources\ServiceRecords\Tables\ServiceRecordsTable;
use App\Models\ServiceRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Operator-logged vehicle service history (operator feature #10). Owner-only —
 * fleet data, like Vehicles — but NOT plan-gated: logging is free on every
 * plan so an operator never loses this lock-in data by downgrading. Only the
 * reminder/auto-block sweep (ProcessVehicleMaintenanceJob) checks the plan.
 */
class ServiceRecordResource extends Resource
{
    protected static ?string $model = ServiceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'service_type';

    public static function getNavigationLabel(): string
    {
        return __('panel.nav_service_records');
    }

    /** Owner-only, free on every plan — logging must stay available even on Basic. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ServiceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceRecordsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceRecords::route('/'),
            'create' => CreateServiceRecord::route('/create'),
            'edit' => EditServiceRecord::route('/{record}/edit'),
        ];
    }
}
