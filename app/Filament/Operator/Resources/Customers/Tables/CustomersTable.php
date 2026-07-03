<?php

namespace App\Filament\Operator\Resources\Customers\Tables;

use App\Enums\BookingStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        // No tenant filter — the BelongsToTenant global scope already limits
        // rows to the current operator's tenant.
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withCount('bookings')
                // Lifetime spend = realised revenue (Active + Completed), matching
                // Customer::totalSpend() and the Reports revenue definition.
                ->withSum(
                    ['bookings as total_spend' => fn (Builder $q) => $q->whereIn('status', [BookingStatus::Active, BookingStatus::Completed])],
                    'total',
                ))
            ->columns([
                TextColumn::make('name')
                    ->label(__('panel.customer_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label(__('panel.customer_phone'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('panel.customer_email'))
                    ->toggleable(),
                IconColumn::make('is_blacklisted')
                    ->label(__('panel.blacklisted'))
                    ->boolean()
                    ->trueColor('danger')
                    ->falseColor('gray'),
                TextColumn::make('bookings_count')
                    ->label(__('panel.bookings_count'))
                    ->sortable(),
                TextColumn::make('total_spend')
                    ->label(__('panel.total_spend'))
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('panel.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
