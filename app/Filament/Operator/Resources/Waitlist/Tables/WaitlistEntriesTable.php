<?php

namespace App\Filament\Operator\Resources\Waitlist\Tables;

use App\Models\WaitlistEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WaitlistEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.name')
                    ->label(__('panel.waitlist_vehicle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('panel.waitlist_name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('panel.waitlist_email'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('phone')
                    ->label(__('panel.waitlist_phone'))
                    ->placeholder('—'),
                TextColumn::make('dates')
                    ->label(__('panel.waitlist_dates'))
                    ->state(fn (WaitlistEntry $record): string => $record->start_date === null
                        ? __('panel.waitlist_any_dates')
                        : $record->start_date->toDateString().' → '.$record->end_date?->toDateString()),
                TextColumn::make('notified_at')
                    ->label(__('panel.waitlist_notified_at'))
                    ->dateTime()
                    ->placeholder(__('panel.waitlist_waiting'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('panel.waitlist_joined_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            // created_at is the FIFO key, so the list reads in the order people
            // will actually be told.
            ->defaultSort('created_at', 'asc')
            ->filters([
                SelectFilter::make('state')
                    ->label(__('panel.waitlist_state'))
                    ->options([
                        'waiting' => __('panel.waitlist_waiting'),
                        'notified' => __('panel.waitlist_notified'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'waiting' => $query->whereNull('notified_at'),
                        'notified' => $query->whereNotNull('notified_at'),
                        default => $query,
                    }),
                // The two entry types answer different questions — "when is my
                // fleet oversubscribed" vs "which car do people miss" — and a
                // null start_date is what separates them.
                SelectFilter::make('type')
                    ->label(__('panel.waitlist_type'))
                    ->options([
                        'dates' => __('panel.waitlist_type_dates'),
                        'stock_alert' => __('panel.waitlist_type_stock_alert'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'dates' => $query->whereNotNull('start_date'),
                        'stock_alert' => $query->whereNull('start_date'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
