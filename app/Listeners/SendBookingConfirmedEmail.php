<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Mail\BookingConfirmedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

class SendBookingConfirmedEmail implements ShouldQueue
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        if (! $booking->customer_email) {
            return;
        }

        Mail::to($booking->customer_email)
            ->locale($booking->locale)
            ->queue(new BookingConfirmedMail($booking));
    }
}
