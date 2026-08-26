<?php

namespace App\Filament\Operator\Resources\Bookings\Pages;

use App\Exceptions\InvalidBookingWindowException;
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
     * vehicle lock, window check and availability re-check run before any row is
     * inserted. Unlike the customer path, this one may start in the past — the
     * walk-in who drove off at 09:00 is entered at 11:00 — but the max-duration
     * cap applies here too.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return resolve(BookingService::class)->createManual($data);
        } catch (VehicleNotAvailableException|PromoCodeInvalidException|InvalidBookingWindowException $e) {
            Notification::make()
                ->title(match (true) {
                    $e instanceof PromoCodeInvalidException => __('panel.promo_invalid_title'),
                    $e instanceof InvalidBookingWindowException => __('panel.invalid_booking_window_title'),
                    default => __('panel.vehicle_not_available_title'),
                })
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();

            // halt() throws — this line is unreachable but satisfies the return type.
            return new Booking;
        }
    }
}
