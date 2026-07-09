<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Mail\BookingCancelledMail;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingCancelledNotifications implements ShouldQueue
{
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;
        $cancelledBy = $event->cancelledBy;

        if ($booking->customer_email) {
            Mail::to($booking->customer_email)
                ->locale($booking->locale ?? 'sq')
                ->queue(new BookingCancelledMail($booking));
        }

        if ($cancelledBy === 'customer') {
            $operators = User::where('tenant_id', $booking->tenant_id)->get();
            $vehicleDisplay = $booking->vehicle->name;

            foreach ($operators as $operator) {
                Mail::to($operator->email)
                    ->locale($operator->locale ?? 'sq')
                    ->queue(new BookingCancelledMail($booking));

                Notification::make()
                    ->title(__('emails.booking_cancelled.bell_title'))
                    ->body($booking->customer_name.' · '.$vehicleDisplay)
                    ->warning()
                    ->sendToDatabase($operator);
            }
        }
    }
}
