<?php

namespace App\Filament\Operator\Resources\Vehicles\Tables;

use App\Enums\VehicleCategory;
use App\Enums\VehicleStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        // No tenant filter — the BelongsToTenant global scope already limits
        // rows to the current operator's tenant.
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('')
                    ->collection('vehicle_photos')
                    ->limit(1)
                    ->circular(),
                TextColumn::make('name')
                    ->label(__('panel.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label(__('panel.category'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('panel.status'))
                    ->badge(),
                TextColumn::make('daily_rate')
                    ->label(__('panel.daily_rate'))
                    ->money('eur')
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label(__('panel.public'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('panel.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('panel.category'))
                    ->options(VehicleCategory::class),
                SelectFilter::make('status')
                    ->label(__('panel.status'))
                    ->options(VehicleStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
