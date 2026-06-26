<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('domains.domain')
                    ->label('Subdomain')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('plan')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('trial_ends_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                self::approveAction(),
                self::suspendAction(),
                self::reactivateAction(),
                self::rejectAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Approve a pending operator (pending -> active).
     *
     * TODO (Notifications step): send "operator approved" email.
     * TODO (Billing step): start the trial / subscription on approval.
     */
    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === 'pending')
            ->action(fn (Tenant $record) => $record->update(['status' => 'active']));
    }

    protected static function suspendAction(): Action
    {
        return Action::make('suspend')
            ->icon('heroicon-o-pause-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === 'active')
            ->action(fn (Tenant $record) => $record->update(['status' => 'suspended']));
    }

    protected static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->icon('heroicon-o-play-circle')
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => $record->status === 'suspended')
            ->action(fn (Tenant $record) => $record->update(['status' => 'active']));
    }

    /**
     * Reject an operator (cancel them).
     *
     * TODO (Notifications step): send "operator rejected" email.
     */
    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Tenant $record): bool => in_array($record->status, ['pending', 'active', 'suspended'], true))
            ->action(fn (Tenant $record) => $record->update(['status' => 'cancelled']));
    }
}
