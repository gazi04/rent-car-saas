<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Mail\BookingReceivedMail;
use App\Mail\NewBookingAlertMail;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingReceivedNotifications implements ShouldQueue
{
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;
        $operators = User::where('tenant_id', $booking->tenant_id)->get();

        if ($booking->customer_email) {
            Mail::to($booking->customer_email)
                ->locale($booking->locale)
                ->queue(new BookingReceivedMail($booking));
        }

        $vehicleDisplay = $booking->vehicle->name;

        foreach ($operators as $operator) {
            Mail::to($operator->email)
                ->queue(new NewBookingAlertMail($booking));

            Notification::make()
                ->title(__('emails.new_booking_alert.bell_title'))
                ->body($booking->customer_name.' · '.$vehicleDisplay)
                ->success()
                ->sendToDatabase($operator);
        }
    }
}
