<?php

namespace App\Filament\Resources\Activities\Tables;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ActivitiesTable
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
                TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'reactivated' => 'success',
                        'suspended', 'rejected' => 'danger',
                        'recorded_payment' => 'info',
                        'impersonated' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label('Admin')
                    ->placeholder('System')
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Tenant')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('properties')
                    ->label('Details')
                    ->formatStateUsing(fn ($state): string => filled((array) $state) ? (string) json_encode($state) : '—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('description')
                    ->label('Action')
                    ->options([
                        'approved' => 'Approved',
                        'suspended' => 'Suspended',
                        'reactivated' => 'Reactivated',
                        'rejected' => 'Rejected',
                        'recorded_payment' => 'Recorded payment',
                        'impersonated' => 'Impersonated',
                    ]),
                // causer is a polymorphic (morphTo) relation, so a relationship()
                // filter can't resolve a single related model — filter on the raw
                // id against the known admin users instead.
                SelectFilter::make('causer_id')
                    ->label('Admin')
                    ->options(fn (): array => User::query()
                        ->where('role', 'admin')
                        ->pluck('name', 'id')
                        ->all()),
            ]);
    }
}
