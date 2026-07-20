<?php

namespace App\Filament\Operator\Resources\Customers\RelationManagers;

use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only booking history on the customer view page. Bookings are created
 * through BookingService (public + walk-in), never here — rows link out to the
 * booking's own view page.
 */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('panel.nav_bookings');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('reference')
                    ->label(__('panel.reference')),
                TextColumn::make('vehicle.name')
                    ->label(__('panel.vehicle')),
                TextColumn::make('start_date')
                    ->label(__('panel.start_date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label(__('panel.end_date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('panel.status'))
                    ->badge(),
                TextColumn::make('total')
                    ->label(__('panel.total'))
                    ->money('eur')
                    ->sortable(),
            ]);
    }
}
