<?php

namespace App\Filament\Operator\Resources\PromoCodes\Tables;

use App\Models\PromoCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromoCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('panel.promo_code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('panel.promo_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => __('panel.promo_'.$state)),
                TextColumn::make('value')
                    ->label(__('panel.promo_value')),
                TextColumn::make('uses')
                    ->label(__('panel.promo_uses'))
                    ->state(fn (PromoCode $record): string => $record->redeemedUsesCount().($record->max_uses !== null ? ' / '.$record->max_uses : '')),
                TextColumn::make('expires_at')
                    ->label(__('panel.promo_expires_at'))
                    ->date()
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label(__('panel.promo_is_active'))
                    ->boolean(),
            ])
            ->defaultSort('code', 'asc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
