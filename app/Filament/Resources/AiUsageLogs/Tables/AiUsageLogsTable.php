<?php

namespace App\Filament\Resources\AiUsageLogs\Tables;

use App\Models\AiUsageLog;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class AiUsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->placeholder('Platform')
                    ->searchable(),
                TextColumn::make('feature')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'listing' => 'info',
                        'summary' => 'success',
                        'pricing' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('model')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('total_tokens')
                    ->label('Tokens')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('estimated_cost')
                    ->label('Est. cost')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('EUR')),
            ])
            // Group by tenant (opt-in, no default) so the admin can read each
            // tenant's summed tokens/cost — the per-tenant total. Grouping on
            // tenant_id keeps null-tenant "Platform" rows in their own group.
            ->groups([
                Group::make('tenant_id')
                    ->label('Tenant')
                    ->getTitleFromRecordUsing(fn (AiUsageLog $record): string => $record->tenant_id === null ? 'Platform' : $record->tenant->name),
            ])
            ->filters([
                SelectFilter::make('feature')
                    ->options([
                        'listing' => 'Listing writer',
                        'summary' => 'Business summary',
                        'pricing' => 'Pricing suggestion',
                    ]),
                // tenant is a plain BelongsTo (not polymorphic), so relationship()
                // resolves cleanly here — unlike the audit log's morphTo causer.
                SelectFilter::make('tenant')
                    ->relationship('tenant', 'name'),
            ]);
    }
}
