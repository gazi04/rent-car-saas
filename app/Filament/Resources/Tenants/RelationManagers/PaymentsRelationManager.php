<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tenants\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only payment history. Recording happens exclusively through the
 * "Record payment" table action on TenantsTable, so the period-stacking and
 * reactivation logic lives in one place.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('period_end', 'desc')
            ->columns([
                TextColumn::make('period_start')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('plan')
                    ->badge(),
                TextColumn::make('method')
                    ->badge(),
                TextColumn::make('amount')
                    ->money('EUR'),
                TextColumn::make('recordedBy.name')
                    ->label('Recorded by'),
                TextColumn::make('note')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Recorded at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
