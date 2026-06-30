<?php

namespace App\Filament\Operator\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\RentalAgreementService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        // No tenant filter — the BelongsToTenant global scope already limits
        // rows to the current operator's tenant.
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('total')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BookingStatus::class),
                SelectFilter::make('vehicle_id')
                    ->relationship('vehicle', 'name')
                    ->label('Vehicle'),
                Filter::make('date_range')
                    ->label('Start date range')
                    ->form([
                        DateTimePicker::make('from')
                            ->label('From')
                            ->seconds(false),
                        DateTimePicker::make('until')
                            ->label('Until')
                            ->seconds(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $v) => $q->where('start_date', '>=', $v))
                            ->when($data['until'], fn ($q, $v) => $q->where('start_date', '<=', $v));
                    }),
            ])
            ->recordActions([
                self::confirmAction(),
                self::rejectAction(),
                self::markActiveAction(),
                self::completeAction(),
                self::cancelAction(),
                self::downloadAgreementAction(),
                ViewAction::make(),
            ]);
    }

    /**
     * Pending → Confirmed.
     * TODO (Notifications step): BookingService::confirm() will dispatch BookingConfirmed event.
     */
    protected static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Pending)
            ->action(function (Booking $record): void {
                app(BookingService::class)->confirm($record);
            });
    }

    /**
     * Pending → Cancelled.
     * TODO (Notifications step): BookingService::reject() will dispatch BookingRejected event.
     */
    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Pending)
            ->action(function (Booking $record): void {
                app(BookingService::class)->reject($record);
            });
    }

    /**
     * Confirmed → Active. Collects optional start odometer + timestamp.
     */
    protected static function markActiveAction(): Action
    {
        return Action::make('mark_active')
            ->label('Mark active')
            ->icon(Heroicon::OutlinedPlayCircle)
            ->color('info')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Confirmed)
            ->schema([
                DateTimePicker::make('started_at')
                    ->label('Pickup time')
                    ->default(now())
                    ->seconds(false),
                TextInput::make('start_odometer')
                    ->label('Odometer at pickup (km)')
                    ->numeric()
                    ->minValue(0),
            ])
            ->action(function (Booking $record, array $data): void {
                app(BookingService::class)->markActive(
                    $record,
                    filled($data['started_at']) ? Carbon::parse($data['started_at']) : null,
                    filled($data['start_odometer']) ? (int) $data['start_odometer'] : null,
                );
            });
    }

    /**
     * Active → Completed. Collects optional return odometer + timestamp.
     */
    protected static function completeAction(): Action
    {
        return Action::make('complete')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Active)
            ->schema([
                DateTimePicker::make('completed_at')
                    ->label('Return time')
                    ->default(now())
                    ->seconds(false),
                TextInput::make('end_odometer')
                    ->label('Odometer at return (km)')
                    ->numeric()
                    ->minValue(0),
            ])
            ->action(function (Booking $record, array $data): void {
                app(BookingService::class)->complete(
                    $record,
                    filled($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
                    filled($data['end_odometer']) ? (int) $data['end_odometer'] : null,
                );
            });
    }

    protected static function downloadAgreementAction(): Action
    {
        return Action::make('agreement')
            ->label('Download agreement')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(fn (Booking $record): bool => in_array($record->status, [
                BookingStatus::Confirmed,
                BookingStatus::Active,
                BookingStatus::Completed,
            ], true))
            ->action(function (Booking $record): mixed {
                $contract = app(RentalAgreementService::class)->generate($record);

                return Storage::download($contract->path, "agreement-{$record->reference}.pdf");
            });
    }

    /**
     * Any non-Completed status → Cancelled.
     * TODO (Notifications step): BookingService::cancel() will dispatch BookingCancelled event.
     */
    protected static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Booking $record): bool => ! in_array($record->status, [
                BookingStatus::Completed,
                BookingStatus::Cancelled,
            ], true))
            ->action(function (Booking $record): void {
                try {
                    app(BookingService::class)->cancel($record);
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
