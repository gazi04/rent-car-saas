<?php

namespace App\Filament\Operator\Resources\Bookings\Pages;

use App\Exceptions\PromoCodeInvalidException;
use App\Exceptions\VehicleNotAvailableException;
use App\Filament\Operator\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * Routes the manual booking through BookingService::createManual() so the
     * vehicle lock and availability re-check run before any row is inserted.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(BookingService::class)->createManual($data);
        } catch (VehicleNotAvailableException|PromoCodeInvalidException $e) {
            Notification::make()
                ->title($e instanceof PromoCodeInvalidException ? __('panel.promo_invalid_title') : 'Vehicle not available')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();

            // halt() throws — this line is unreachable but satisfies the return type.
            return new Booking;
        }
    }
}
