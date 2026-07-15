<?php

namespace App\Filament\Resources\AiUsageLogs;

use App\Filament\Resources\AiUsageLogs\Pages\ListAiUsageLogs;
use App\Filament\Resources\AiUsageLogs\Tables\AiUsageLogsTable;
use App\Models\AiUsageLog;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only admin view of AI token usage + estimated cost per call (§15.3),
 * attributed to a tenant and feature. No create/edit/delete — rows are written
 * by the RecordAiUsage listener and are immutable.
 */
class AiUsageLogResource extends Resource
{
    protected static ?string $model = AiUsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'AI usage';

    protected static ?string $modelLabel = 'AI usage entry';

    protected static ?int $navigationSort = 91;

    public static function table(Table $table): Table
    {
        return AiUsageLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListAiUsageLogs::route('/'),
        ];
    }
}
