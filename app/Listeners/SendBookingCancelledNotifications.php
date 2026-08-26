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

        if (filled($booking->customer_email)) {
            Mail::to($booking->customer_email)
                ->locale($booking->locale ?? 'sq')
                ->queue(new BookingCancelledMail($booking));
        }

        // Only two actors owe the operator anything: the customer, who made a
        // decision the operator must react to, and the expiry sweep, where a car
        // silently returns to the fleet. An operator cancelling needs no telling.
        if ($cancelledBy !== 'customer' && $cancelledBy !== 'system') {
            return;
        }

        $operators = User::query()->where('tenant_id', $booking->tenant_id)->get();
        $vehicleDisplay = $booking->vehicle->name;
        $bellTitle = $cancelledBy === 'system'
            ? __('panel.booking_expired_bell_title')
            : __('emails.booking_cancelled.bell_title');

        foreach ($operators as $operator) {
            // An expiry is nobody's decision, so there is nothing to confirm to
            // the operator by email — the bell is enough.
            if ($cancelledBy === 'customer') {
                Mail::to($operator->email)
                    ->locale($operator->locale ?? 'sq')
                    ->queue(new BookingCancelledMail($booking));
            }

            Notification::make()
                ->title($bellTitle)
                ->body($booking->customer_name.' · '.$vehicleDisplay)
                ->warning()
                ->sendToDatabase($operator);
        }
    }
}
