<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Today's schedule: vehicles going out (pickups) and coming back (returns)
 * today, in one actionable list that links straight to the booking. Tenant
 * -scoped via BelongsToTenant. Not plan-gated.
 */
class TodaysMovementsWidget extends TableWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panel.dashboard_todays_movements'))
            ->emptyStateHeading(__('panel.dashboard_nothing_today'))
            ->query($this->movementsQuery())
            ->defaultSort('start_date', 'asc')
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('type')
                    ->label(__('panel.type'))
                    ->badge()
                    ->state(fn (Booking $record): string => $this->isPickup($record)
                        ? __('panel.type_pickup')
                        : __('panel.type_return'))
                    ->color(fn (Booking $record): string => $this->isPickup($record) ? 'info' : 'success'),
                TextColumn::make('reference')
                    ->label(__('panel.reference')),
                TextColumn::make('vehicle.name')
                    ->label(__('panel.vehicle')),
                TextColumn::make('customer_name')
                    ->label(__('panel.customer_name')),
                TextColumn::make('time')
                    ->label(__('panel.time'))
                    ->state(fn (Booking $record): string => ($this->isPickup($record) ? $record->start_date : $record->end_date)->format('H:i')),
                TextColumn::make('status')
                    ->label(__('panel.status'))
                    ->badge(),
            ]);
    }

    /**
     * A row is a pickup when its status is Pending/Confirmed (matched the query's
     * pickup branch) and a return when Active (matched the return branch) — the
     * two branches filter on mutually exclusive statuses, so status alone tells
     * us which branch selected this row. A same-day rental (Active, starts and
     * ends today) is correctly a return: it's already been picked up.
     */
    protected function isPickup(Booking $booking): bool
    {
        return in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }

    /** @return Builder<Booking> */
    protected function movementsQuery(): Builder
    {
        $startOfToday = today();
        $endOfToday = now()->endOfDay();

        return Booking::query()
            ->with('vehicle')
            ->where(function (Builder $query) use ($startOfToday, $endOfToday): void {
                $query
                    ->where(function (Builder $pickup) use ($startOfToday, $endOfToday): void {
                        $pickup
                            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                            ->whereBetween('start_date', [$startOfToday, $endOfToday]);
                    })
                    ->orWhere(function (Builder $return) use ($startOfToday, $endOfToday): void {
                        $return
                            ->where('status', BookingStatus::Active)
                            ->whereBetween('end_date', [$startOfToday, $endOfToday]);
                    });
            });
    }
}
