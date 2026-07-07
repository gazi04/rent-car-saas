<?php

namespace App\Filament\Operator\Widgets;

use App\Enums\BookingStatus;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Filament\Support\HelpAction;
use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bookings that need an operator decision now: those awaiting confirmation
 * (Pending) and active rentals whose return date has passed (overdue). Links
 * straight to the booking. Tenant-scoped via BelongsToTenant. Not plan-gated.
 */
class NeedsAttentionWidget extends TableWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('panel.dashboard_needs_attention'))
            ->headerActions([
                HelpAction::make('needs_attention'),
            ])
            ->emptyStateHeading(__('panel.dashboard_all_caught_up'))
            ->query($this->attentionQuery())
            ->defaultSort('start_date', 'asc')
            ->recordUrl(fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('issue')
                    ->label(__('panel.issue'))
                    ->badge()
                    ->state(fn (Booking $record): string => $this->isOverdue($record)
                        ? __('panel.issue_overdue')
                        : __('panel.issue_awaiting'))
                    ->color(fn (Booking $record): string => $this->isOverdue($record) ? 'danger' : 'warning'),
                TextColumn::make('reference')
                    ->label(__('panel.reference')),
                TextColumn::make('vehicle.name')
                    ->label(__('panel.vehicle')),
                TextColumn::make('customer_name')
                    ->label(__('panel.customer_name')),
                TextColumn::make('date')
                    ->label(__('panel.date'))
                    ->state(fn (Booking $record): string => ($this->isOverdue($record) ? $record->end_date : $record->start_date)->format('d M Y H:i')),
                TextColumn::make('status')
                    ->label(__('panel.status'))
                    ->badge(),
            ]);
    }

    protected function isOverdue(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Active && $booking->end_date->isPast();
    }

    /** @return Builder<Booking> */
    protected function attentionQuery(): Builder
    {
        return Booking::query()
            ->with('vehicle')
            ->where(function (Builder $query): void {
                $query
                    ->where('status', BookingStatus::Pending)
                    ->orWhere(function (Builder $overdue): void {
                        $overdue
                            ->where('status', BookingStatus::Active)
                            ->where('end_date', '<', now());
                    });
            });
    }
}
