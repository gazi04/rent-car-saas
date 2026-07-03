<?php

namespace App\Filament\Operator\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\RentalAgreementService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ViewBooking extends ViewRecord
{
    protected static string $resource = BookingResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('agreement')
                ->label(__('panel.action_agreement'))
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('gray')
                ->visible(fn (): bool => $this->record instanceof Booking && in_array($this->record->status, [
                    BookingStatus::Confirmed,
                    BookingStatus::Active,
                    BookingStatus::Completed,
                ], true))
                ->action(function (): mixed {
                    /** @var Booking $booking */
                    $booking = $this->record;
                    $contract = app(RentalAgreementService::class)->generate($booking);

                    return Storage::download($contract->path, "agreement-{$booking->reference}.pdf");
                }),
        ];
    }
}
